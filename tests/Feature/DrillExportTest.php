<?php

namespace Tests\Feature;

use App\Console\Commands\ExportDrillData;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DrillExportTest extends TestCase
{
    use RefreshDatabase;

    private array $berkas = [];

    private int $kategoriId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kategoriId = DB::table('categories')->insertGetId([
            'name' => 'Kebakaran', 'slug' => 'kebakaran', 'base_priority' => 5,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->berkas as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    private function buatLaporan(array $ubah = []): int
    {
        return DB::table('incidents')->insertGetId(array_merge([
            'category_id' => $this->kategoriId,
            'location' => null,
            'location_source' => 'manual',
            'reference' => strtoupper(substr(md5(uniqid('', true)), 0, 10)),
            'status' => 'baru',
            'trust_score' => 70,
            'priority_score' => 4,
            'reported_at' => '2026-10-20 10:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ], $ubah));
    }

    private function catat(int $id, string $ke, int $detik): void
    {
        $laporan = DB::table('incidents')->where('id', $id)->first();

        DB::table('incident_logs')->insert([
            'incident_id' => $id,
            'action' => 'ubah_status',
            'to_status' => $ke,
            'created_at' => Carbon::parse($laporan->reported_at)->addSeconds($detik)->format('Y-m-d H:i:s'),
        ]);
    }

    private function ekspor(): array
    {
        $dasar = tempnam(sys_get_temp_dir(), 'ekspor');
        $path = $dasar.'.csv';
        $this->berkas = array_merge($this->berkas, [$dasar, $path]);

        $this->artisan('gladi:ekspor', ['--berkas' => $path])->assertExitCode(0);

        $isi = (string) preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($path));
        $baris = array_map(
            fn ($b) => str_getcsv($b, ',', '"', ''),
            array_values(array_filter(explode("\n", trim($isi))))
        );

        return ['isi' => $isi, 'baris' => $baris, 'kolom' => array_flip($baris[0])];
    }

    public function test_ekspor_tidak_memuat_data_pribadi_dan_kolomnya_tetap(): void
    {
        $this->buatLaporan([
            'reporter_phone' => '08123456789',
            'reporter_hash' => str_repeat('a', 64),
            'description' => 'Rahasia keterangan pribadi',
            'landmark_text' => 'Depan rumah Pak X',
            'location' => DB::raw('ST_SetSRID(ST_MakePoint(110.18, -7.32), 4326)::geography'),
            'location_source' => 'gps',
            'location_accuracy_m' => 20,
            'reference' => 'ABCDE23456',
        ]);

        $hasil = $this->ekspor();

        $this->assertSame(ExportDrillData::HEADER, $hasil['baris'][0]);

        foreach (['08123456789', 'Rahasia keterangan', 'Depan rumah', '110.18', '-7.32', 'ABCDE23456', str_repeat('a', 64)] as $terlarang) {
            $this->assertStringNotContainsString($terlarang, $hasil['isi'], $terlarang);
        }
    }

    public function test_ekspor_menghitung_detik_tiap_tahap_dan_mengubah_jam_ke_wib(): void
    {
        $id = $this->buatLaporan(['status' => 'selesai']);
        $this->catat($id, 'diverifikasi', 30);
        $this->catat($id, 'ditugaskan', 90);
        $this->catat($id, 'ditangani', 300);
        $this->catat($id, 'selesai', 900);

        $hasil = $this->ekspor();
        $baris = $hasil['baris'][1];
        $k = $hasil['kolom'];

        $this->assertSame('30', $baris[$k['detik_diverifikasi']]);
        $this->assertSame('90', $baris[$k['detik_ditugaskan']]);
        $this->assertSame('300', $baris[$k['detik_ditangani']]);
        $this->assertSame('900', $baris[$k['detik_selesai']]);
        $this->assertSame('selesai', $baris[$k['status_akhir']]);
        $this->assertSame(
            Carbon::parse('2026-10-20 10:00:00', config('app.timezone'))->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            $baris[$k['waktu_lapor_wib']]
        );
    }

    public function test_ekspor_menandai_sos_korban_luka_dan_penandaan(): void
    {
        $this->buatLaporan([
            'reported_at' => '2026-10-20 10:00:00',
            'description' => 'LAPORAN SOS (satu ketukan)',
        ]);
        $this->buatLaporan([
            'reported_at' => '2026-10-20 11:00:00',
            'has_casualty' => true,
            'location_source' => 'gps',
            'location_accuracy_m' => 18.4,
            'flag_reason' => 'sengaja_palsu',
        ]);
        $this->buatLaporan(['reported_at' => '2026-10-20 12:00:00', 'has_casualty' => false]);

        $hasil = $this->ekspor();
        $k = $hasil['kolom'];

        $this->assertCount(4, $hasil['baris']);

        $this->assertSame('ya', $hasil['baris'][1][$k['sos']]);
        $this->assertSame('tidak tahu', $hasil['baris'][1][$k['korban_luka']]);

        $this->assertSame('ya', $hasil['baris'][2][$k['korban_luka']]);
        $this->assertSame('tidak', $hasil['baris'][2][$k['sos']]);
        $this->assertSame('sengaja_palsu', $hasil['baris'][2][$k['ditandai']]);
        $this->assertSame('18', $hasil['baris'][2][$k['akurasi_m']]);

        $this->assertSame('tidak', $hasil['baris'][3][$k['korban_luka']]);
    }

    public function test_ekspor_tanpa_laporan_hanya_berisi_judul_kolom(): void
    {
        DB::table('incidents')->delete();

        $hasil = $this->ekspor();

        $this->assertCount(1, $hasil['baris']);
        $this->assertSame(ExportDrillData::HEADER, $hasil['baris'][0]);
    }

    public function test_selisih_negatif_dan_tahap_belum_tercapai_dikosongkan(): void
    {
        $id = $this->buatLaporan(['status' => 'ditangani']);
        $this->catat($id, 'diverifikasi', 20);
        $this->catat($id, 'ditangani', -30);

        $hasil = $this->ekspor();
        $baris = $hasil['baris'][1];
        $k = $hasil['kolom'];

        $this->assertSame('20', $baris[$k['detik_diverifikasi']]);
        $this->assertSame('', $baris[$k['detik_ditangani']]);
        $this->assertSame('', $baris[$k['detik_ditugaskan']]);
        $this->assertSame('', $baris[$k['detik_selesai']]);
    }
}

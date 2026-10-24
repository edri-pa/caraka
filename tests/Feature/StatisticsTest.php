<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\IncidentStatistics;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StatisticsTest extends TestCase
{
    use RefreshDatabase;

    private int $kategoriId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kategoriId = DB::table('categories')->insertGetId([
            'name' => 'Kategori Uji',
            'slug' => 'uji',
            'base_priority' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ringkasan(?\DateTimeInterface $sejak = null): array
    {
        return app(IncidentStatistics::class)->summary($sejak);
    }

    private function buatLaporan(array $ubah = []): int
    {
        return DB::table('incidents')->insertGetId(array_merge([
            'category_id' => $this->kategoriId,
            'location' => null,
            'location_source' => 'manual',
            'reference' => strtoupper(substr(md5(uniqid('', true)), 0, 10)),
            'status' => 'baru',
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
            'user_id' => null,
            'action' => 'ubah_status',
            'to_status' => $ke,
            'created_at' => Carbon::parse($laporan->reported_at)->addSeconds($detik)->format('Y-m-d H:i:s'),
        ]);
    }

    private function masuk(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));
    }

    public function test_tamu_diarahkan_ke_halaman_login(): void
    {
        $this->get('/statistik')->assertRedirect(route('login'));
    }

    public function test_responder_dan_warga_ditolak(): void
    {
        foreach (['responder', 'warga'] as $peran) {
            $this->masuk($peran);

            $this->get('/statistik')->assertForbidden();
        }
    }

    public function test_dispatcher_dan_admin_boleh_membuka(): void
    {
        foreach (['dispatcher', 'admin'] as $peran) {
            $this->masuk($peran);

            $this->get('/statistik')->assertOk();
        }
    }

    public function test_waktu_tiap_tahap_dihitung_terpisah_dengan_median(): void
    {
        foreach ([[10, 30, 60], [20, 30, 120], [40, 300, 600]] as [$verif, $tugas, $tangani]) {
            $id = $this->buatLaporan(['status' => 'ditangani']);
            $this->catat($id, 'diverifikasi', $verif);
            $this->catat($id, 'ditugaskan', $tugas);
            $this->catat($id, 'ditangani', $tangani);
        }

        $tahap = $this->ringkasan()['stages'];

        $this->assertSame(20.0, $tahap['diverifikasi']['median']);
        $this->assertSame(30.0, $tahap['ditugaskan']['median']);
        $this->assertSame(120.0, $tahap['ditangani']['median']);
        $this->assertSame(3, $tahap['ditangani']['count']);
        $this->assertSame(260, $tahap['ditangani']['mean']);
        $this->assertSame(60, $tahap['ditangani']['min']);
        $this->assertSame(600, $tahap['ditangani']['max']);
    }

    public function test_tahap_dihitung_dari_kejadian_pertama_saja(): void
    {
        $id = $this->buatLaporan(['status' => 'ditugaskan']);
        $this->catat($id, 'diverifikasi', 10);
        $this->catat($id, 'ditugaskan', 100);
        $this->catat($id, 'diverifikasi', 150);
        $this->catat($id, 'ditugaskan', 500);

        $tahap = $this->ringkasan()['stages'];

        $this->assertSame(1, $tahap['ditugaskan']['count']);
        $this->assertSame(100.0, $tahap['ditugaskan']['median']);
        $this->assertSame(10.0, $tahap['diverifikasi']['median']);
    }

    public function test_laporan_yang_ditandai_tidak_dihitung_tetapi_jumlahnya_tampil(): void
    {
        $sah = $this->buatLaporan(['status' => 'ditangani']);
        $this->catat($sah, 'ditangani', 60);

        $palsu = $this->buatLaporan(['status' => 'ditangani', 'flag_reason' => 'sengaja_palsu']);
        $this->catat($palsu, 'ditangani', 9999);

        $this->buatLaporan(['flag_reason' => 'duplikat']);

        $ringkasan = $this->ringkasan();

        $this->assertSame(1, $ringkasan['total']);
        $this->assertSame(3, $ringkasan['total_semua']);
        $this->assertSame(60.0, $ringkasan['stages']['ditangani']['median']);
        $this->assertSame(1, $ringkasan['ditandai']['sengaja_palsu']);
        $this->assertSame(1, $ringkasan['ditandai']['lainnya']);
    }

    public function test_selisih_waktu_negatif_diabaikan(): void
    {
        $id = $this->buatLaporan(['status' => 'ditangani']);
        $this->catat($id, 'ditangani', -30);

        $tahap = $this->ringkasan()['stages']['ditangani'];

        $this->assertSame(0, $tahap['count']);
        $this->assertNull($tahap['median']);
    }

    public function test_laporan_yang_belum_mencapai_tahap_dihitung_masih_berjalan(): void
    {
        $this->buatLaporan(['status' => 'baru']);

        $b = $this->buatLaporan(['status' => 'ditugaskan']);
        $this->catat($b, 'diverifikasi', 10);
        $this->catat($b, 'ditugaskan', 20);

        $tahap = $this->ringkasan()['stages'];

        $this->assertSame(1, $tahap['diverifikasi']['pending']);
        $this->assertSame(1, $tahap['ditugaskan']['pending']);
        $this->assertSame(2, $tahap['ditangani']['pending']);
    }

    public function test_laporan_batal_dan_tidak_terbukti_bukan_masih_berjalan(): void
    {
        $this->buatLaporan(['status' => 'dibatalkan']);
        $this->buatLaporan(['status' => 'tidak_terbukti']);

        foreach ($this->ringkasan()['stages'] as $tahap) {
            $this->assertSame(0, $tahap['pending']);
        }
    }

    public function test_filter_periode_membatasi_laporan(): void
    {
        $this->buatLaporan(['reported_at' => now()->subDays(10)->format('Y-m-d H:i:s')]);
        $this->buatLaporan(['reported_at' => now()->subDay()->format('Y-m-d H:i:s')]);

        $this->assertSame(2, $this->ringkasan()['total']);
        $this->assertSame(1, $this->ringkasan(now()->subDays(7))['total']);

        $this->masuk('dispatcher');

        $this->get('/statistik?periode=7')->assertOk()->assertSee('Sampel kecil');
    }

    public function test_gps_dan_sos_dihitung(): void
    {
        $this->buatLaporan(['location_source' => 'gps', 'location_accuracy_m' => 20]);
        $this->buatLaporan(['location_source' => 'gps', 'location_accuracy_m' => 40]);
        $this->buatLaporan(['location_source' => 'manual']);
        $this->buatLaporan(['location_source' => 'manual', 'description' => 'LAPORAN SOS (satu ketukan)']);

        $ringkasan = $this->ringkasan();

        $this->assertSame(2, $ringkasan['gps']['dengan_gps']);
        $this->assertSame(2, $ringkasan['gps']['manual']);
        $this->assertSame(30.0, $ringkasan['gps']['median_akurasi']);
        $this->assertSame(1, $ringkasan['sos']);
    }

    public function test_halaman_menampilkan_tiga_tahap_dan_peringatan_sampel_kecil(): void
    {
        $id = $this->buatLaporan(['status' => 'ditangani']);
        $this->catat($id, 'diverifikasi', 10);
        $this->catat($id, 'ditugaskan', 30);
        $this->catat($id, 'ditangani', 125);
        $this->masuk('dispatcher');

        $this->get('/statistik')
            ->assertOk()
            ->assertSee('Waktu sampai diverifikasi')
            ->assertSee('Waktu sampai ditugaskan')
            ->assertSee('Waktu sampai ditangani')
            ->assertSee('ukuran utama')
            ->assertSee('2 mnt 5 dtk')
            ->assertSee('Sampel kecil');
    }

    public function test_halaman_tidak_menampilkan_data_pribadi(): void
    {
        $this->buatLaporan([
            'reporter_phone' => '08123456789',
            'description' => 'rahasia-keterangan',
        ]);
        $this->masuk('dispatcher');

        $this->get('/statistik')
            ->assertOk()
            ->assertDontSee('08123456789')
            ->assertDontSee('rahasia-keterangan');
    }

    public function test_halaman_kosong_menampilkan_pesan(): void
    {
        $this->masuk('dispatcher');

        $this->get('/statistik')->assertOk()->assertSee('Belum ada laporan');
    }

    public function test_daftar_dispatcher_menautkan_ke_statistik(): void
    {
        $this->masuk('dispatcher');

        $this->get('/dispatcher/laporan')->assertOk()->assertSee('/statistik', false);
    }

    public function test_median_bilangan_ganjil_genap_dan_kosong(): void
    {
        $layanan = app(IncidentStatistics::class);

        $this->assertNull($layanan->median([]));
        $this->assertSame(5.0, $layanan->median([5]));
        $this->assertSame(2.0, $layanan->median([1, 3]));
        $this->assertSame(2.0, $layanan->median([9, 1, 2]));
    }

    public static function durasi(): array
    {
        return [
            'kosong' => [null, '-'],
            'detik' => [5, '5 dtk'],
            'tepat satu menit' => [60, '1 mnt'],
            'menit dan detik' => [65, '1 mnt 5 dtk'],
            'tepat satu jam' => [3600, '1 jam'],
            'jam dan menit' => [3725, '1 jam 2 mnt'],
            'dibulatkan' => [29.6, '30 dtk'],
        ];
    }

    #[DataProvider('durasi')]
    public function test_format_durasi(?float $detik, string $diharapkan): void
    {
        $this->assertSame($diharapkan, app(IncidentStatistics::class)->formatDuration($detik));
    }
}

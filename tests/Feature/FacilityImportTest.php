<?php

namespace Tests\Feature;

use App\Services\FacilityCsvImporter;
use App\Services\NearestFacilityFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FacilityImportTest extends TestCase
{
    use RefreshDatabase;

    private const KOLOM = [
        'nama', 'jenis', 'wilayah', 'kecamatan', 'lintang', 'bujur',
        'buka_24_jam', 'ada_igd', 'kontak', 'kontak_terverifikasi', 'sumber', 'tanggal_cek',
    ];

    private array $berkas = [];

    protected function tearDown(): void
    {
        foreach ($this->berkas as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    private function baris(array $ubah = []): array
    {
        return array_merge([
            'nama' => 'Fasilitas Uji A',
            'jenis' => 'puskesmas',
            'wilayah' => 'Temanggung',
            'kecamatan' => '',
            'lintang' => '-7.32',
            'bujur' => '110.18',
            'buka_24_jam' => 'tidak',
            'ada_igd' => 'tidak',
            'kontak' => 'DEMO-0001',
            'kontak_terverifikasi' => '',
            'sumber' => 'Uji tes',
            'tanggal_cek' => now()->format('Y-m-d'),
        ], $ubah);
    }

    private function tulis(array $data, string $pemisah = ',', bool $bom = false, ?array $kolom = null): string
    {
        $kolom ??= self::KOLOM;
        $isi = implode($pemisah, $kolom)."\n";

        foreach ($data as $baris) {
            $isi .= implode($pemisah, array_map(fn ($k) => $baris[$k] ?? '', $kolom))."\n";
        }

        if ($bom) {
            $isi = "\xEF\xBB\xBF".$isi;
        }

        $path = tempnam(sys_get_temp_dir(), 'fas');
        file_put_contents($path, $isi);
        $this->berkas[] = $path;

        return $path;
    }

    private function baca(string $path): array
    {
        return app(FacilityCsvImporter::class)->parse($path);
    }

    private function galat(array $hasil): string
    {
        return implode(' | ', $hasil['errors']);
    }

    public function test_csv_valid_terbaca_dengan_benar(): void
    {
        $hasil = $this->baca($this->tulis([
            $this->baris(['nama' => 'Fasilitas Uji A']),
            $this->baris([
                'nama' => 'Fasilitas Uji B', 'jenis' => 'rumah_sakit', 'lintang' => '-7.30',
                'bujur' => '110.20', 'buka_24_jam' => 'ya', 'ada_igd' => 'ya',
            ]),
        ]));

        $this->assertSame([], $hasil['errors']);
        $this->assertCount(2, $hasil['rows']);

        $b = $hasil['rows'][1];
        $this->assertSame('rumah_sakit', $b['type']);
        $this->assertEqualsWithDelta(-7.30, $b['lat'], 0.0001);
        $this->assertEqualsWithDelta(110.20, $b['lng'], 0.0001);
        $this->assertTrue($b['is_24h']);
        $this->assertTrue($b['has_igd']);
    }

    public function test_pemisah_titik_koma_dan_koma_desimal_terbaca(): void
    {
        $hasil = $this->baca($this->tulis([$this->baris(['lintang' => '-7,3168', 'bujur' => '110,1760'])], ';'));

        $this->assertSame([], $hasil['errors']);
        $this->assertEqualsWithDelta(-7.3168, $hasil['rows'][0]['lat'], 0.00001);
        $this->assertEqualsWithDelta(110.1760, $hasil['rows'][0]['lng'], 0.00001);
    }

    public function test_penanda_bom_pada_header_diabaikan(): void
    {
        $hasil = $this->baca($this->tulis([$this->baris()], ',', true));

        $this->assertSame([], $hasil['errors']);
        $this->assertCount(1, $hasil['rows']);
    }

    public function test_kolom_wajib_yang_hilang_ditolak(): void
    {
        $hasil = $this->baca($this->tulis([$this->baris()], ',', false, ['nama', 'jenis', 'bujur', 'sumber', 'tanggal_cek']));

        $this->assertStringContainsString('lintang', $this->galat($hasil));
        $this->assertSame([], $hasil['rows']);
    }

    public function test_lintang_bujur_tertukar_ditolak(): void
    {
        $hasil = $this->baca($this->tulis([$this->baris(['lintang' => '110.18', 'bujur' => '-7.32'])]));

        $this->assertStringContainsString('tertukar', $this->galat($hasil));
    }

    public function test_lintang_positif_ditolak_dengan_petunjuk_minus(): void
    {
        $hasil = $this->baca($this->tulis([$this->baris(['lintang' => '7.32'])]));

        $this->assertStringContainsString('minus', $this->galat($hasil));
    }

    public function test_koordinat_di_luar_kotak_pengaman_ditolak(): void
    {
        $hasil = $this->baca($this->tulis([$this->baris(['lintang' => '-2.5'])]));

        $this->assertStringContainsString('di luar', $this->galat($hasil));
    }

    public function test_jenis_tidak_dikenal_ditolak(): void
    {
        $hasil = $this->baca($this->tulis([$this->baris(['jenis' => 'mal'])]));

        $this->assertStringContainsString("jenis 'mal'", $this->galat($hasil));
    }

    public function test_sumber_dan_tanggal_cek_wajib_dan_valid(): void
    {
        $hasil = $this->baca($this->tulis([
            $this->baris(['nama' => 'Tanpa Sumber', 'sumber' => '']),
            $this->baris(['nama' => 'Tanggal Salah', 'tanggal_cek' => 'kemarin']),
            $this->baris(['nama' => 'Tanggal Depan', 'tanggal_cek' => now()->addDays(3)->format('Y-m-d')]),
        ]));

        $this->assertCount(3, $hasil['errors']);
        $this->assertStringContainsString('sumber wajib', $this->galat($hasil));
        $this->assertStringContainsString('TTTT-BB-HH', $this->galat($hasil));
        $this->assertStringContainsString('masa depan', $this->galat($hasil));
    }

    public function test_kontak_nyata_tanpa_verifikasi_ditolak(): void
    {
        $hasil = $this->baca($this->tulis([$this->baris(['kontak' => '00000000'])]));

        $this->assertStringContainsString('DEMO-', $this->galat($hasil));
    }

    public function test_status_demo_mengikuti_kontak(): void
    {
        $hasil = $this->baca($this->tulis([
            $this->baris(['nama' => 'Kontak Demo', 'kontak' => 'DEMO-0002']),
            $this->baris(['nama' => 'Tanpa Kontak', 'kontak' => '']),
            $this->baris(['nama' => 'Kontak Terverifikasi', 'kontak' => '00000000', 'kontak_terverifikasi' => 'ya']),
        ]));

        $this->assertSame([], $hasil['errors']);
        $this->assertTrue($hasil['rows'][0]['is_demo']);
        $this->assertTrue($hasil['rows'][1]['is_demo']);
        $this->assertNull($hasil['rows'][1]['phone']);
        $this->assertFalse($hasil['rows'][2]['is_demo']);
    }

    public function test_kecamatan_tidak_dikenal_ditolak_hanya_untuk_wilayah_temanggung(): void
    {
        $districtId = DB::table('districts')->insertGetId([
            'name' => 'Kecamatan Uji',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $hasil = $this->baca($this->tulis([
            $this->baris(['nama' => 'Kecamatan Benar', 'kecamatan' => 'kecamatan uji']),
            $this->baris(['nama' => 'Kecamatan Salah', 'kecamatan' => 'Tidak Ada']),
            $this->baris(['nama' => 'Luar Kabupaten', 'wilayah' => 'Kendal', 'kecamatan' => 'Entah']),
        ]));

        $this->assertCount(1, $hasil['errors']);
        $this->assertStringContainsString("kecamatan 'Tidak Ada'", $this->galat($hasil));
        $this->assertCount(2, $hasil['rows']);
        $this->assertSame($districtId, $hasil['rows'][0]['district_id']);
    }

    public function test_nama_ganda_dalam_berkas_ditolak(): void
    {
        $hasil = $this->baca($this->tulis([
            $this->baris(['nama' => 'Fasilitas Ganda']),
            $this->baris(['nama' => 'fasilitas ganda']),
        ]));

        $this->assertStringContainsString('nama ganda', $this->galat($hasil));
    }

    public function test_instansi_terhubung_sesuai_jenis_fasilitas(): void
    {
        $medis = DB::table('agencies')->insertGetId([
            'name' => 'Instansi Medis', 'type' => 'medis', 'is_demo' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $damkar = DB::table('agencies')->insertGetId([
            'name' => 'Instansi Damkar', 'type' => 'damkar', 'is_demo' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $hasil = $this->baca($this->tulis([
            $this->baris(['nama' => 'Puskesmas Uji', 'jenis' => 'puskesmas']),
            $this->baris(['nama' => 'Pos Uji', 'jenis' => 'pos_damkar']),
            $this->baris(['nama' => 'Polsek Uji', 'jenis' => 'polsek']),
            $this->baris(['nama' => 'Lain Uji', 'jenis' => 'lainnya']),
        ]));

        $this->assertSame([], $hasil['errors']);
        $this->assertSame($medis, $hasil['rows'][0]['agency_id']);
        $this->assertSame($damkar, $hasil['rows'][1]['agency_id']);
        $this->assertNull($hasil['rows'][2]['agency_id']);
        $this->assertNull($hasil['rows'][3]['agency_id']);
        $this->assertCount(1, $hasil['warnings']);
        $this->assertStringContainsString('polisi', $hasil['warnings'][0]);
    }

    public function test_perintah_hanya_memeriksa_tanpa_opsi_simpan(): void
    {
        $path = $this->tulis([$this->baris()]);

        $this->artisan('fasilitas:impor', ['file' => $path])->assertExitCode(0);

        $this->assertDatabaseCount('facilities', 0);
    }

    public function test_perintah_menyimpan_dengan_opsi_simpan_beserta_lokasi(): void
    {
        $path = $this->tulis([$this->baris(['nama' => 'Fasilitas Simpan', 'lintang' => '-7.30', 'bujur' => '110.20'])]);

        $this->artisan('fasilitas:impor', ['file' => $path, '--simpan' => true])->assertExitCode(0);

        $this->assertDatabaseCount('facilities', 1);

        $titik = DB::selectOne('select ST_X(location::geometry) as lng, ST_Y(location::geometry) as lat from facilities');
        $this->assertEqualsWithDelta(110.20, (float) $titik->lng, 0.0001);
        $this->assertEqualsWithDelta(-7.30, (float) $titik->lat, 0.0001);

        $this->assertDatabaseHas('facilities', [
            'name' => 'Fasilitas Simpan',
            'region' => 'Temanggung',
            'source' => 'Uji tes',
            'is_demo' => true,
        ]);
    }

    public function test_satu_baris_salah_membatalkan_seluruh_impor(): void
    {
        $path = $this->tulis([
            $this->baris(['nama' => 'Baris Benar']),
            $this->baris(['nama' => 'Baris Salah', 'lintang' => '110.18', 'bujur' => '-7.32']),
        ]);

        $this->artisan('fasilitas:impor', ['file' => $path, '--simpan' => true])->assertExitCode(1);

        $this->assertDatabaseCount('facilities', 0);
    }

    public function test_impor_ulang_memperbarui_bukan_menggandakan(): void
    {
        $awal = $this->tulis([$this->baris(['nama' => 'Fasilitas Ulang', 'ada_igd' => 'tidak'])]);
        $ubah = $this->tulis([$this->baris(['nama' => 'Fasilitas Ulang', 'ada_igd' => 'ya'])]);

        $this->artisan('fasilitas:impor', ['file' => $awal, '--simpan' => true])->assertExitCode(0);
        $this->artisan('fasilitas:impor', ['file' => $ubah, '--simpan' => true])->assertExitCode(0);

        $this->assertDatabaseCount('facilities', 1);
        $this->assertTrue((bool) DB::table('facilities')->value('has_igd'));
    }

    public function test_data_uji_tidak_tersentuh(): void
    {
        DB::table('facilities')->insert([
            'name' => 'UJI Lama',
            'type' => 'puskesmas',
            'region' => 'UJI',
            'location' => DB::raw('ST_SetSRID(ST_MakePoint(110.18, -7.32), 4326)::geography'),
            'is_active' => true,
            'is_demo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $path = $this->tulis([$this->baris(['nama' => 'Fasilitas Baru'])]);

        $this->artisan('fasilitas:impor', ['file' => $path, '--simpan' => true])->assertExitCode(0);

        $this->assertDatabaseHas('facilities', ['name' => 'UJI Lama', 'region' => 'UJI']);
        $this->assertDatabaseCount('facilities', 2);
    }

    public function test_fasilitas_hasil_impor_ditemukan_pencari_terdekat(): void
    {
        $path = $this->tulis([
            $this->baris(['nama' => 'Dekat Uji', 'lintang' => '-7.32', 'bujur' => '110.18']),
            $this->baris(['nama' => 'Jauh Uji', 'lintang' => '-7.40', 'bujur' => '110.30']),
        ]);

        $this->artisan('fasilitas:impor', ['file' => $path, '--simpan' => true])->assertExitCode(0);

        $hasil = app(NearestFacilityFinder::class)->find(110.182, -7.320, [], 3000);

        $this->assertSame(['Dekat Uji'], $hasil->pluck('name')->all());
    }
}

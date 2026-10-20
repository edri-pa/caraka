<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FacilityRecommender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FacilityRecommenderTest extends TestCase
{
    use RefreshDatabase;

    private const IGD = 'Rumah sakit dengan IGD terdekat';
    private const KESEHATAN = 'Puskesmas dan klinik terdekat';
    private const POLISI = 'Kantor polisi terdekat';
    private const DAMKAR = 'Pos pemadam terdekat';
    private const BPBD = 'Kantor BPBD';

    public static function kasus(): array
    {
        return [
            'medis' => ['medis', null, [self::IGD, self::KESEHATAN]],
            'kecelakaan dengan korban' => ['kecelakaan-lalu-lintas', true, [self::IGD, self::KESEHATAN, self::POLISI]],
            'kecelakaan korban tidak diketahui' => ['kecelakaan-lalu-lintas', null, [self::IGD, self::KESEHATAN, self::POLISI]],
            'kecelakaan tanpa korban' => ['kecelakaan-lalu-lintas', false, [self::POLISI]],
            'kebakaran' => ['kebakaran', null, [self::DAMKAR, self::IGD]],
            'kebakaran tanpa korban' => ['kebakaran', false, [self::DAMKAR]],
            'bencana dengan korban' => ['bencana-alam', true, [self::BPBD, self::IGD]],
            'bencana tanpa korban' => ['bencana-alam', false, [self::BPBD]],
            'kriminal dengan korban' => ['kriminal', true, [self::POLISI, self::IGD]],
            'kriminal korban tidak diketahui' => ['kriminal', null, [self::POLISI]],
            'lainnya' => ['lainnya', null, []],
            'kategori tidak dikenal' => ['ngawur', true, []],
        ];
    }

    #[DataProvider('kasus')]
    public function test_kelompok_fasilitas_sesuai_kejadian(?string $slug, ?bool $korban, array $judul): void
    {
        $hasil = array_map(
            fn ($kelompok) => $kelompok['title'],
            app(FacilityRecommender::class)->groupsFor($slug, $korban)
        );

        $this->assertSame($judul, $hasil);
    }

    private function buatKategori(string $slug): int
    {
        return DB::table('categories')->insertGetId([
            'name' => 'Kategori '.$slug,
            'slug' => $slug,
            'base_priority' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function buatFasilitas(string $nama, string $jenis, float $lng, float $lat, bool $igd = false, bool $aktif = true): int
    {
        return DB::table('facilities')->insertGetId([
            'name' => $nama,
            'type' => $jenis,
            'region' => 'UJI',
            'location' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)::geography', $lng, $lat)),
            'phone' => 'DEMO-0001',
            'is_24h' => $igd,
            'has_igd' => $igd,
            'is_active' => $aktif,
            'is_demo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function buatLaporan(int $kategoriId, ?bool $korban, bool $gps = true): int
    {
        return DB::table('incidents')->insertGetId([
            'category_id' => $kategoriId,
            'location' => $gps ? DB::raw('ST_SetSRID(ST_MakePoint(110.182, -7.320), 4326)::geography') : null,
            'location_accuracy_m' => $gps ? 25 : null,
            'location_source' => $gps ? 'gps' : 'manual',
            'reference' => strtoupper(substr(md5(uniqid('', true)), 0, 10)),
            'has_casualty' => $korban,
            'status' => 'baru',
            'reported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function masuk(string $role, ?int $agencyId = null): User
    {
        $user = User::factory()->create(['role' => $role, 'agency_id' => $agencyId]);
        $this->actingAs($user);

        return $user;
    }

    private function seedFasilitasUji(): void
    {
        $this->buatFasilitas('Puskesmas A', 'puskesmas', 110.180, -7.320);
        $this->buatFasilitas('RS B', 'rumah_sakit', 110.190, -7.320, true);
        $this->buatFasilitas('RS C Tanpa IGD', 'rumah_sakit', 110.185, -7.320, false);
        $this->buatFasilitas('Polsek D', 'polsek', 110.220, -7.320);
    }

    private function namaDi(array $info, string $judul): array
    {
        foreach ($info['groups'] as $kelompok) {
            if ($kelompok['title'] === $judul) {
                return array_column($kelompok['items'], 'name');
            }
        }

        return ['(kelompok tidak ada)'];
    }

    public function test_laporan_tanpa_koordinat_tidak_punya_peta(): void
    {
        $id = $this->buatLaporan($this->buatKategori('kecelakaan-lalu-lintas'), true, false);

        $this->assertSame(['map' => null, 'groups' => []], app(FacilityRecommender::class)->forIncident($id));
    }

    public function test_fasilitas_diurutkan_dan_difilter_sesuai_kelompok(): void
    {
        $this->seedFasilitasUji();
        $id = $this->buatLaporan($this->buatKategori('kecelakaan-lalu-lintas'), true);

        $info = app(FacilityRecommender::class)->forIncident($id);

        $this->assertSame(['RS B'], $this->namaDi($info, self::IGD));
        $this->assertSame(['Puskesmas A'], $this->namaDi($info, self::KESEHATAN));
        $this->assertSame(['Polsek D'], $this->namaDi($info, self::POLISI));
    }

    public function test_fasilitas_nonaktif_tidak_disarankan(): void
    {
        $this->seedFasilitasUji();
        DB::table('facilities')->where('name', 'RS B')->update(['is_active' => false]);
        $id = $this->buatLaporan($this->buatKategori('kecelakaan-lalu-lintas'), true);

        $info = app(FacilityRecommender::class)->forIncident($id);

        $this->assertSame([], $this->namaDi($info, self::IGD));
    }

    public function test_kecelakaan_tanpa_korban_hanya_menampilkan_kantor_polisi(): void
    {
        $this->seedFasilitasUji();
        $id = $this->buatLaporan($this->buatKategori('kecelakaan-lalu-lintas'), false);

        $info = app(FacilityRecommender::class)->forIncident($id);

        $this->assertSame([self::POLISI], array_column($info['groups'], 'title'));
    }

    public function test_data_peta_memuat_kejadian_dan_fasilitas_tanpa_duplikat(): void
    {
        $this->seedFasilitasUji();
        $id = $this->buatLaporan($this->buatKategori('medis'), null);

        $peta = app(FacilityRecommender::class)->forIncident($id)['map'];

        $this->assertEqualsWithDelta(110.182, $peta['kejadian']['lng'], 0.0001);
        $this->assertEqualsWithDelta(-7.320, $peta['kejadian']['lat'], 0.0001);
        $this->assertSame(25, $peta['kejadian']['akurasi']);
        $this->assertCount(2, $peta['fasilitas']);
        $this->assertEqualsWithDelta(110.190, $peta['fasilitas'][0]['lng'], 0.0001);
    }

    public function test_halaman_detail_memuat_peta_dan_daftar_fasilitas(): void
    {
        $this->seedFasilitasUji();
        $id = $this->buatLaporan($this->buatKategori('kecelakaan-lalu-lintas'), true);
        $this->masuk('dispatcher');

        $this->get("/staf/laporan/{$id}")
            ->assertOk()
            ->assertSee('id="peta"', false)
            ->assertSee('id="peta-data"', false)
            ->assertSee(self::IGD)
            ->assertSee('RS B')
            ->assertSee('garis lurus');
    }

    public function test_halaman_detail_laporan_manual_menjelaskan_peta_tidak_tersedia(): void
    {
        $id = $this->buatLaporan($this->buatKategori('kecelakaan-lalu-lintas'), true, false);
        $this->masuk('dispatcher');

        $this->get("/staf/laporan/{$id}")
            ->assertOk()
            ->assertSee('lokasi manual')
            ->assertDontSee('id="peta"', false);
    }

    public function test_responder_yang_ditugaskan_melihat_peta(): void
    {
        $this->seedFasilitasUji();
        $id = $this->buatLaporan($this->buatKategori('kecelakaan-lalu-lintas'), true);
        $agency = DB::table('agencies')->insertGetId([
            'name' => 'Instansi Uji',
            'type' => 'medis',
            'is_demo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('incident_agencies')->insert([
            'incident_id' => $id,
            'agency_id' => $agency,
            'role' => 'utama',
            'assigned_at' => now(),
        ]);
        $this->masuk('responder', $agency);

        $this->get("/staf/laporan/{$id}")
            ->assertOk()
            ->assertSee('id="peta"', false);
    }

    public function test_nama_fasilitas_berbahaya_tidak_memecah_skrip(): void
    {
        $this->buatFasilitas('</script><b>x</b>', 'rumah_sakit', 110.190, -7.320, true);
        $id = $this->buatLaporan($this->buatKategori('kecelakaan-lalu-lintas'), true);
        $this->masuk('dispatcher');

        $this->get("/staf/laporan/{$id}")
            ->assertOk()
            ->assertDontSee('</script><b>x</b>', false);
    }

    public function test_kontak_demo_ditandai_dan_tidak_menjadi_tautan_telepon(): void
    {
        $this->seedFasilitasUji();
        $id = $this->buatLaporan($this->buatKategori('kecelakaan-lalu-lintas'), true);
        $this->masuk('dispatcher');

        $this->get("/staf/laporan/{$id}")
            ->assertSee('kontak simulasi')
            ->assertDontSee('href="tel:', false);
    }
}

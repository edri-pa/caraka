<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResponderTaskListTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/responder/tugas';

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

    private function buatInstansi(string $nama): int
    {
        return DB::table('agencies')->insertGetId([
            'name' => $nama,
            'type' => 'medis',
            'is_demo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function buatLaporan(array $ubah = []): int
    {
        return DB::table('incidents')->insertGetId(array_merge([
            'category_id' => $this->kategoriId,
            'location' => null,
            'location_source' => 'manual',
            'landmark_text' => 'Depan pasar',
            'reference' => strtoupper(substr(md5(uniqid('', true)), 0, 10)),
            'status' => 'ditugaskan',
            'trust_score' => 50,
            'priority_score' => 3,
            'reported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $ubah));
    }

    private function tugaskan(int $laporanId, int $instansiId, string $peran = 'utama'): void
    {
        DB::table('incident_agencies')->insert([
            'incident_id' => $laporanId,
            'agency_id' => $instansiId,
            'role' => $peran,
            'assigned_at' => now(),
        ]);
    }

    private function masuk(string $role, ?int $instansiId = null): User
    {
        $user = User::factory()->create(['role' => $role, 'agency_id' => $instansiId]);
        $this->actingAs($user);

        return $user;
    }

    public function test_tamu_diarahkan_ke_halaman_login(): void
    {
        $this->get(self::URL)->assertRedirect(route('login'));
    }

    public function test_peran_selain_responder_ditolak(): void
    {
        foreach (['dispatcher', 'admin', 'warga'] as $peran) {
            $this->masuk($peran);

            $this->get(self::URL)->assertForbidden();
        }
    }

    public function test_responder_tanpa_instansi_melihat_penjelasan(): void
    {
        $this->masuk('responder', null);

        $this->get(self::URL)
            ->assertOk()
            ->assertSee('belum terhubung ke instansi');
    }

    public function test_hanya_tugas_aktif_milik_instansinya_yang_tampil(): void
    {
        $milik = $this->buatInstansi('Instansi Milik');
        $lain = $this->buatInstansi('Instansi Lain');

        $ditugaskan = $this->buatLaporan(['reference' => 'AAAAAAAAA1', 'status' => 'ditugaskan']);
        $ditangani = $this->buatLaporan(['reference' => 'AAAAAAAAA2', 'status' => 'ditangani']);
        $baru = $this->buatLaporan(['reference' => 'AAAAAAAAA3', 'status' => 'baru']);
        $punyaLain = $this->buatLaporan(['reference' => 'AAAAAAAAA4', 'status' => 'ditugaskan']);
        $selesai = $this->buatLaporan(['reference' => 'AAAAAAAAA5', 'status' => 'selesai']);

        $this->tugaskan($ditugaskan, $milik);
        $this->tugaskan($ditangani, $milik, 'pendukung');
        $this->tugaskan($baru, $milik);
        $this->tugaskan($punyaLain, $lain);
        $this->tugaskan($selesai, $milik);
        $this->masuk('responder', $milik);

        $this->get(self::URL)
            ->assertOk()
            ->assertSee('AAAAAAAAA1')
            ->assertSee('AAAAAAAAA2')
            ->assertDontSee('AAAAAAAAA3')
            ->assertDontSee('AAAAAAAAA4')
            ->assertDontSee('AAAAAAAAA5');
    }

    public function test_riwayat_menampilkan_laporan_yang_sudah_ditutup(): void
    {
        $milik = $this->buatInstansi('Instansi Milik');
        $selesai = $this->buatLaporan(['reference' => 'BBBBBBBBB1', 'status' => 'selesai']);
        $aktif = $this->buatLaporan(['reference' => 'BBBBBBBBB2', 'status' => 'ditugaskan']);
        $this->tugaskan($selesai, $milik);
        $this->tugaskan($aktif, $milik);
        $this->masuk('responder', $milik);

        $this->get(self::URL.'?tab=riwayat')
            ->assertOk()
            ->assertSee('BBBBBBBBB1')
            ->assertDontSee('BBBBBBBBB2');
    }

    public function test_prioritas_tertinggi_ditampilkan_lebih_dulu(): void
    {
        $milik = $this->buatInstansi('Instansi Milik');
        $rendah = $this->buatLaporan(['reference' => 'CCCCCCCCC1', 'priority_score' => 2]);
        $tinggi = $this->buatLaporan(['reference' => 'CCCCCCCCC2', 'priority_score' => 5]);
        $this->tugaskan($rendah, $milik);
        $this->tugaskan($tinggi, $milik);
        $this->masuk('responder', $milik);

        $this->get(self::URL)->assertSeeInOrder(['CCCCCCCCC2', 'CCCCCCCCC1']);
    }

    public function test_daftar_tidak_membocorkan_data_pribadi(): void
    {
        $milik = $this->buatInstansi('Instansi Milik');
        $id = $this->buatLaporan([
            'reporter_phone' => '08000000005',
            'description' => 'rahasia-keterangan',
        ]);
        $this->tugaskan($id, $milik);
        $this->masuk('responder', $milik);

        $this->get(self::URL)
            ->assertOk()
            ->assertDontSee('08000000005')
            ->assertDontSee('rahasia-keterangan');
    }

    public function test_daftar_memuat_tautan_ke_detail(): void
    {
        $milik = $this->buatInstansi('Instansi Milik');
        $id = $this->buatLaporan();
        $this->tugaskan($id, $milik);
        $this->masuk('responder', $milik);

        $this->get(self::URL)->assertSee("/staf/laporan/{$id}", false);
    }

    public function test_detail_menampilkan_tautan_kembali_ke_tugas_saya(): void
    {
        $milik = $this->buatInstansi('Instansi Milik');
        $id = $this->buatLaporan();
        $this->tugaskan($id, $milik);
        $this->masuk('responder', $milik);

        $this->get("/staf/laporan/{$id}")
            ->assertOk()
            ->assertSee('Tugas saya')
            ->assertSee('/responder/tugas', false);
    }

    public function test_halaman_sementara_sudah_dihapus(): void
    {
        foreach (['/uji-reverb', '/uji-gps', '/uji-peran/dispatcher', '/uji-peran/responder', '/uji-peran/admin', '/uji-keluar'] as $path) {
            $this->get($path)->assertNotFound();
        }

        $this->assertFalse(class_exists(\App\Events\PingTest::class));
    }
}

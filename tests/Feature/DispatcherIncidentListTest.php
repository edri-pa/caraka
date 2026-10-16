<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DispatcherIncidentListTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/dispatcher/laporan';

    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->categoryId = DB::table('categories')->insertGetId([
            'name' => 'Kategori Uji',
            'slug' => 'uji',
            'base_priority' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function incident(array $overrides = []): string
    {
        $reference = $overrides['reference'] ?? strtoupper(substr(md5(uniqid('', true)), 0, 10));

        DB::table('incidents')->insert(array_merge([
            'category_id' => $this->categoryId,
            'location' => null,
            'location_source' => 'manual',
            'landmark_text' => 'Depan pasar',
            'status' => 'baru',
            'trust_score' => 50,
            'priority_score' => 3,
            'reported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides, ['reference' => $reference]));

        return $reference;
    }

    private function loginAs(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));
    }

    public function test_tamu_diarahkan_ke_halaman_login(): void
    {
        $this->get(self::URL)->assertRedirect(route('login'));
    }

    public function test_responder_ditolak(): void
    {
        $this->loginAs('responder');

        $this->get(self::URL)->assertForbidden();
    }

    public function test_warga_ditolak(): void
    {
        $this->loginAs('warga');

        $this->get(self::URL)->assertForbidden();
    }

    public function test_dispatcher_melihat_data_lengkap_termasuk_nomor_hp(): void
    {
        $ref = $this->incident(['reporter_phone' => '08000000002', 'description' => 'Deskripsi uji']);
        $this->loginAs('dispatcher');

        $this->get(self::URL)
            ->assertOk()
            ->assertSee($ref)
            ->assertSee('08000000002')
            ->assertSee('Deskripsi uji');
    }

    public function test_admin_boleh_membuka_daftar(): void
    {
        $this->loginAs('admin');

        $this->get(self::URL)->assertOk();
    }

    public function test_bawaan_hanya_menampilkan_laporan_aktif_dan_filter_bekerja(): void
    {
        $selesai = $this->incident(['status' => 'selesai']);
        $baru = $this->incident(['status' => 'baru']);
        $this->loginAs('dispatcher');

        $this->get(self::URL)->assertSee($baru)->assertDontSee($selesai);
        $this->get(self::URL.'?status=selesai')->assertSee($selesai)->assertDontSee($baru);
        $this->get(self::URL.'?status=semua')->assertSee($selesai)->assertSee($baru);
    }

    public function test_prioritas_tertinggi_ditampilkan_lebih_dulu(): void
    {
        $this->incident(['reference' => 'AAAAAAAAAA', 'priority_score' => 2]);
        $this->incident(['reference' => 'BBBBBBBBBB', 'priority_score' => 5]);
        $this->loginAs('dispatcher');

        $this->get(self::URL)->assertSeeInOrder(['BBBBBBBBBB', 'AAAAAAAAAA']);
    }

    public function test_filter_tidak_dikenal_kembali_ke_bawaan(): void
    {
        $ref = $this->incident();
        $this->loginAs('dispatcher');

        $this->get(self::URL.'?status=bukan-filter')->assertOk()->assertSee($ref);
    }

    public function test_nomor_hp_tidak_menjadi_tautan_telepon(): void
    {
        $this->incident(['reporter_phone' => '08000000002']);
        $this->loginAs('dispatcher');

        $this->get(self::URL)->assertDontSee('href="tel:', false);
    }

    public function test_isian_publik_di_escape_sehingga_skrip_tidak_berjalan(): void
    {
        $this->incident(['description' => '<script>alert(1)</script>']);
        $this->loginAs('dispatcher');

        $this->get(self::URL)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_pesan_kosong_saat_belum_ada_laporan(): void
    {
        $this->loginAs('dispatcher');

        $this->get(self::URL)->assertSee('Belum ada laporan');
    }
}

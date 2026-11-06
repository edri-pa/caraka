<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GlassThemeTest extends TestCase
{
    use RefreshDatabase;

    private function masuk(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user);

        return $user;
    }

    public function test_berkas_tema_ringan_dan_tanpa_sumber_luar(): void
    {
        $css = file_get_contents(public_path('css/caraka.css'));

        $this->assertLessThan(20000, strlen($css));

        foreach (['@import', 'http://', 'https://', 'url('] as $terlarang) {
            $this->assertStringNotContainsString($terlarang, $css, $terlarang);
        }
    }

    public function test_tema_memuat_cadangan_aksesibilitas_dan_layar_sempit(): void
    {
        $css = file_get_contents(public_path('css/caraka.css'));

        foreach ([
            'backdrop-filter', '@supports not', 'prefers-reduced-motion', 'prefers-reduced-transparency',
            'focus-visible', 'max-width:719px', '.nav-cair', 'safe-area-inset-bottom',
        ] as $kunci) {
            $this->assertStringContainsString($kunci, $css, $kunci);
        }
    }

    public function test_skrip_navbar_ringan_dan_menghormati_gerak_dikurangi(): void
    {
        $js = file_get_contents(public_path('js/navbar.js'));

        $this->assertLessThan(8000, strlen($js));
        $this->assertStringContainsString('prefers-reduced-motion', $js);
        $this->assertStringContainsString('sessionStorage', $js);
    }

    public function test_halaman_warga_memuat_tema_dan_navbar(): void
    {
        foreach (['/', '/lapor', '/sos', '/privasi', '/login'] as $alamat) {
            $this->get($alamat)
                ->assertOk()
                ->assertSee('css/caraka.css', false)
                ->assertSee('js/navbar.js', false)
                ->assertSee('class="caraka"', false)
                ->assertSee('class="nav-kaca"', false)
                ->assertSee('aria-label="Navigasi utama"', false);
        }
    }

    public function test_halaman_galat_memuat_tema(): void
    {
        $this->get('/alamat-yang-tidak-ada')
            ->assertNotFound()
            ->assertSee('css/caraka.css', false)
            ->assertSee('class="nav-kaca"', false);
    }

    public function test_navbar_warga_menandai_halaman_aktif(): void
    {
        $sos = $this->get('/sos')->getContent();
        $this->assertMatchesRegularExpression('/class="nav-sos"[^>]*aria-current="page"/', $sos);

        $beranda = $this->get('/')->getContent();
        $this->assertMatchesRegularExpression('/<a href="[^"]*" aria-current="page">Beranda<\/a>/', $beranda);
        $this->assertDoesNotMatchRegularExpression('/class="nav-sos"[^>]*aria-current/', $beranda);
    }

    public function test_navbar_petugas_sesuai_peran(): void
    {
        $this->masuk('dispatcher');
        $this->get('/dispatcher/laporan')
            ->assertOk()
            ->assertSee('aria-label="Navigasi petugas"', false)
            ->assertSee('/statistik', false)
            ->assertSee('class="nav-keluar"', false);

        $this->masuk('responder');
        $this->get('/responder/tugas')
            ->assertOk()
            ->assertSee('Tugas saya')
            ->assertDontSee('/statistik', false);
    }

    public function test_halaman_petugas_memuat_tema_staf(): void
    {
        foreach (['dispatcher', 'admin'] as $peran) {
            $this->masuk($peran);

            foreach (['/dispatcher/laporan', '/statistik'] as $alamat) {
                $this->get($alamat)
                    ->assertOk()
                    ->assertSee('css/caraka.css', false)
                    ->assertSee('class="caraka staf"', false)
                    ->assertSee('class="nav-kaca"', false);
            }
        }

        $this->masuk('responder');
        $this->get('/responder/tugas')->assertOk()->assertSee('class="caraka staf"', false);
    }

    public function test_halaman_detail_laporan_memuat_tema(): void
    {
        $kategori = DB::table('categories')->insertGetId([
            'name' => 'Uji', 'slug' => 'uji', 'base_priority' => 3,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $id = DB::table('incidents')->insertGetId([
            'category_id' => $kategori,
            'location' => null,
            'location_source' => 'manual',
            'reference' => 'ABCDE23456',
            'status' => 'baru',
            'reported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->masuk('dispatcher');

        $this->get("/staf/laporan/{$id}")
            ->assertOk()
            ->assertSee('css/caraka.css', false)
            ->assertSee('class="caraka staf"', false)
            ->assertSee('class="nav-kaca"', false);
    }

    public function test_tombol_sos_tetap_menonjol_di_beranda_dan_navbar(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('class="tombol sos"', false)
            ->assertSee('class="nav-sos"', false);
    }

    public function test_halaman_tidak_memuat_font_atau_skrip_dari_luar(): void
    {
        foreach (['/', '/lapor', '/sos', '/login'] as $alamat) {
            $this->get($alamat)
                ->assertDontSee('fonts.googleapis', false)
                ->assertDontSee('cdn.', false);
        }
    }
}

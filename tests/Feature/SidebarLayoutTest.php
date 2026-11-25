<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SidebarLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function masuk(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user);

        return $user;
    }

    private function buatLaporan(): int
    {
        $kategori = DB::table('categories')->insertGetId([
            'name' => 'Uji', 'slug' => 'uji', 'base_priority' => 3,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('incidents')->insertGetId([
            'category_id' => $kategori,
            'reference' => 'ABCDE23456',
            'location' => null,
            'location_source' => 'manual',
            'status' => 'baru',
            'reported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_halaman_petugas_memakai_tata_letak_dua_kolom(): void
    {
        $id = $this->buatLaporan();

        foreach (['dispatcher', 'admin'] as $peran) {
            $this->masuk($peran);

            foreach (['/dispatcher/laporan', '/statistik', "/staf/laporan/{$id}"] as $alamat) {
                $this->get($alamat)
                    ->assertOk()
                    ->assertSee('data-tata="sidebar"', false)
                    ->assertSee('id="sidebar"', false)
                    ->assertSee('class="app-isi"', false)
                    ->assertSee('css/sidebar.css', false)
                    ->assertSee('js/sidebar.js', false);
            }
        }

        $this->masuk('responder');

        $this->get('/responder/tugas')
            ->assertOk()
            ->assertSee('data-tata="sidebar"', false)
            ->assertSee('id="sidebar"', false)
            ->assertSee('class="app-isi"', false);
    }

    public function test_menu_berada_di_kiri_sebelum_isi_di_kanan(): void
    {
        $this->masuk('dispatcher');

        $this->get('/dispatcher/laporan')
            ->assertOk()
            ->assertSeeInOrder(['<aside class="side"', 'id="side-tirai"', 'class="app-isi"'], false);
    }

    public function test_menu_sesuai_peran(): void
    {
        foreach (['dispatcher', 'admin'] as $peran) {
            $this->masuk($peran);

            $this->get('/dispatcher/laporan')
                ->assertOk()
                ->assertSee('Laporan masuk')
                ->assertSee('Statistik')
                ->assertDontSee('Tugas saya');
        }

        $this->masuk('responder');

        $this->get('/responder/tugas')
            ->assertOk()
            ->assertSee('Tugas saya')
            ->assertDontSee('Laporan masuk')
            ->assertDontSee('/statistik', false);
    }

    public function test_item_menu_aktif_ditandai(): void
    {
        $id = $this->buatLaporan();
        $this->masuk('dispatcher');

        $daftar = $this->get('/dispatcher/laporan')->getContent();
        $this->assertMatchesRegularExpression('/<a href="[^"]*\/dispatcher\/laporan" aria-current="page"/', $daftar);
        $this->assertDoesNotMatchRegularExpression('/<a href="[^"]*\/statistik" aria-current="page"/', $daftar);

        $statistik = $this->get('/statistik')->getContent();
        $this->assertMatchesRegularExpression('/<a href="[^"]*\/statistik" aria-current="page"/', $statistik);
        $this->assertDoesNotMatchRegularExpression('/<a href="[^"]*\/dispatcher\/laporan" aria-current="page"/', $statistik);

        $detail = $this->get("/staf/laporan/{$id}")->getContent();
        $this->assertMatchesRegularExpression('/<a href="[^"]*\/dispatcher\/laporan" aria-current="page"/', $detail);
    }

    public function test_tombol_sembunyikan_dan_tampilkan_menu_dapat_diakses(): void
    {
        $this->masuk('dispatcher');

        $this->get('/dispatcher/laporan')
            ->assertOk()
            ->assertSee('id="side-tutup" aria-controls="sidebar" aria-expanded="true" aria-label="Sembunyikan menu"', false)
            ->assertSee('id="side-buka" aria-controls="sidebar" aria-expanded="true" aria-label="Tampilkan menu"', false);
    }

    public function test_hanya_satu_formulir_keluar_yang_tampil(): void
    {
        $this->masuk('dispatcher');

        $html = $this->get('/dispatcher/laporan')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'class="nav-keluar"'));
    }

    public function test_preferensi_menu_dibaca_sebelum_halaman_digambar(): void
    {
        $this->masuk('dispatcher');

        $this->get('/dispatcher/laporan')
            ->assertOk()
            ->assertSeeInOrder(["localStorage.getItem('caraka_menu')", '<aside class="side"'], false);
    }

    public function test_halaman_warga_tidak_memakai_sidebar(): void
    {
        foreach (['/', '/lapor', '/sos', '/login'] as $alamat) {
            $this->get($alamat)
                ->assertOk()
                ->assertDontSee('id="sidebar"', false)
                ->assertDontSee('css/sidebar.css', false);
        }
    }

    public function test_navbar_melayang_tidak_dipakai_di_halaman_petugas(): void
    {
        $this->masuk('dispatcher');
        $this->get('/dispatcher/laporan')->assertDontSee('class="nav-kaca"', false);

        $this->masuk('responder');
        $this->get('/responder/tugas')->assertDontSee('class="nav-kaca"', false);
    }

    public function test_lebar_isi_cukup_untuk_tabel_saat_menu_disembunyikan(): void
    {
        $css = file_get_contents(public_path('css/sidebar.css'));

        $this->assertStringContainsString(':not(script){max-width:1680px', $css);
        $this->assertStringNotContainsString(':not(script){max-width:1240px', $css);
    }

    public function test_berkas_css_sidebar_ringan_dan_memuat_aturan_penting(): void
    {
        $css = file_get_contents(public_path('css/sidebar.css'));

        $this->assertLessThan(9000, strlen($css));

        foreach ([
            '@media (max-width:899px)', '@media (min-width:900px)', 'prefers-reduced-motion',
            'prefers-reduced-transparency', '@supports not', 'visibility:hidden', '[data-menu="tersembunyi"]',
        ] as $kunci) {
            $this->assertStringContainsString($kunci, $css, $kunci);
        }

        foreach (['url(', 'http', '@import'] as $terlarang) {
            $this->assertStringNotContainsString($terlarang, $css, $terlarang);
        }
    }

    public function test_berkas_js_sidebar_ringan_dan_menangani_papan_ketik_dan_penyimpanan(): void
    {
        $js = file_get_contents(public_path('js/sidebar.js'));

        $this->assertLessThan(8000, strlen($js));

        foreach (['localStorage', 'sessionStorage', 'Escape', 'matchMedia', 'aria-expanded', 'prefers-reduced-motion'] as $kunci) {
            $this->assertStringContainsString($kunci, $js, $kunci);
        }
    }

    public function test_kaki_versi_berada_di_dalam_kolom_isi(): void
    {
        $this->masuk('dispatcher');

        $this->get('/dispatcher/laporan')
            ->assertOk()
            ->assertSeeInOrder(['class="app-isi"', 'class="versi-app"', '</body>'], false);
    }
}

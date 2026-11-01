<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DocumentationTest extends TestCase
{
    private const BERKAS = [
        'README.md',
        'docs/ARSITEKTUR.md',
        'docs/DEPLOY.md',
        'docs/NASKAH-DEMO.md',
        'docs/RENCANA-GLADI.md',
        'docs/CATATAN-PROYEK.md',
    ];

    public function test_dokumen_wajib_ada_dan_tidak_kosong(): void
    {
        foreach (self::BERKAS as $berkas) {
            $this->assertFileExists(base_path($berkas));
            $this->assertGreaterThan(200, filesize(base_path($berkas)), $berkas.' terlalu pendek');
        }
    }

    public function test_perintah_yang_didokumentasikan_terdaftar_dan_tercantum_di_readme(): void
    {
        $readme = file_get_contents(base_path('README.md'));
        $terdaftar = array_keys(Artisan::all());

        foreach (['fasilitas:impor', 'pengguna:buat', 'laporan:anonimkan', 'keamanan:periksa', 'gladi:reset'] as $perintah) {
            $this->assertContains($perintah, $terdaftar, $perintah.' tidak terdaftar');
            $this->assertStringContainsString($perintah, $readme, $perintah.' tidak ada di README');
        }
    }

    public function test_alamat_yang_didokumentasikan_ada_dan_tercantum_di_readme(): void
    {
        $readme = file_get_contents(base_path('README.md'));
        $alamat = collect(Route::getRoutes()->getRoutes())->map->uri()->all();

        foreach (['lapor', 'sos', 'privasi', 'login', 'dispatcher/laporan', 'statistik', 'responder/tugas'] as $uri) {
            $this->assertContains($uri, $alamat, $uri.' tidak terdaftar');
            $this->assertStringContainsString('/'.$uri, $readme, '/'.$uri.' tidak ada di README');
        }
    }

    public function test_dokumentasi_tidak_memuat_kunci_aplikasi(): void
    {
        $kunci = (string) config('app.key');

        if ($kunci === '') {
            $this->markTestSkipped('APP_KEY kosong.');
        }

        foreach (self::BERKAS as $berkas) {
            $this->assertStringNotContainsString($kunci, file_get_contents(base_path($berkas)), $berkas);
        }
    }
}

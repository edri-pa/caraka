<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    public function test_404_tidak_menampilkan_pesan_internal(): void
    {
        Route::get('/_tes/hilang', fn () => abort(404, 'rahasia-pesan'));

        $this->get('/_tes/hilang')
            ->assertNotFound()
            ->assertSee('Halaman tidak ditemukan')
            ->assertDontSee('rahasia-pesan');
    }

    public function test_alamat_tidak_dikenal_menampilkan_halaman_404_indonesia(): void
    {
        $this->get('/alamat-yang-tidak-ada')
            ->assertNotFound()
            ->assertSee('Halaman tidak ditemukan')
            ->assertSee('PSC 119');
    }

    public function test_403_tampil_dalam_bahasa_indonesia(): void
    {
        $this->actingAs(User::factory()->make(['role' => 'responder']));

        $this->get('/dispatcher/laporan')
            ->assertForbidden()
            ->assertSee('tidak memiliki akses');
    }

    public static function halaman(): array
    {
        return [
            '419' => ['errors.419', 'Sesi telah berakhir'],
            '429' => ['errors.429', 'Terlalu banyak permintaan'],
            '500' => ['errors.500', 'Terjadi kesalahan'],
            '503' => ['errors.503', 'Sistem sedang dalam pemeliharaan'],
        ];
    }

    #[DataProvider('halaman')]
    public function test_halaman_galat_lain_berbahasa_indonesia_dan_memuat_catatan_darurat(string $tampilan, string $judul): void
    {
        $this->view($tampilan)
            ->assertSee($judul)
            ->assertSee('PSC 119')
            ->assertSee('Kembali ke beranda');
    }
}

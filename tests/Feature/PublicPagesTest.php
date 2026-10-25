<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_beranda_menampilkan_tautan_utama(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('/sos', false)
            ->assertSee('/lapor', false)
            ->assertSee('/login', false)
            ->assertSee('/privasi', false)
            ->assertSee('Cek status laporan');
    }

    public function test_cek_status_menormalkan_nomor_referensi(): void
    {
        $this->post('/cek-status', ['referensi' => ' abcde-23456 '])
            ->assertRedirect(route('lapor.show', 'ABCDE23456'));
    }

    public function test_cek_status_menolak_format_salah(): void
    {
        $this->post('/cek-status', ['referensi' => 'abc'])
            ->assertRedirect(route('beranda'))
            ->assertSessionHasErrors('referensi');
    }

    public function test_kebijakan_privasi_tampil_untuk_tamu(): void
    {
        $this->get('/privasi')
            ->assertOk()
            ->assertSee('Data yang dikumpulkan')
            ->assertSee('Berapa lama data disimpan')
            ->assertSee('prototype');
    }

    public function test_halaman_warga_menautkan_ke_kebijakan_privasi(): void
    {
        foreach (['/lapor', '/sos'] as $alamat) {
            $this->get($alamat)->assertOk()->assertSee('/privasi', false);
        }
    }
}

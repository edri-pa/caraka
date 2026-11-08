<?php

namespace Tests\Feature;

use Tests\TestCase;

class GlassThemeContrastTest extends TestCase
{
    private function css(): string
    {
        return file_get_contents(public_path('css/caraka.css'));
    }

    private function warna(string $nama): string
    {
        preg_match('/--'.preg_quote($nama, '/').':(#[0-9a-fA-F]{6})/', $this->css(), $cocok);

        $this->assertNotEmpty($cocok, "Variabel --{$nama} tidak ditemukan");

        return $cocok[1];
    }

    private function luminansi(string $hex): float
    {
        $kanal = array_map(fn ($c) => hexdec($c) / 255, str_split(ltrim($hex, '#'), 2));
        $linear = array_map(fn ($c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4, $kanal);

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }

    private function kontras(string $a, string $b): float
    {
        $la = $this->luminansi($a);
        $lb = $this->luminansi($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    public function test_tautan_di_kotak_pesan_dan_navbar_tidak_memakai_gaya_tautan_umum(): void
    {
        $css = $this->css();

        foreach ([
            'body.caraka .nav-kaca a{text-decoration:none}',
            'body.caraka .catatan a',
            'body.caraka .galat a',
            'body.caraka #banner-pembaruan a',
            'linear-gradient(180deg,var(--m600),var(--m700))',
        ] as $kunci) {
            $this->assertStringContainsString($kunci, $css, $kunci);
        }
    }

    public function test_kontras_warna_utama_memenuhi_batas_wcag(): void
    {
        $putih = '#ffffff';

        $this->assertGreaterThanOrEqual(7.0, $this->kontras($putih, $this->warna('m800')), 'putih di atas merah tua');
        $this->assertGreaterThanOrEqual(4.5, $this->kontras($putih, $this->warna('m700')), 'putih di atas merah ujung tombol');
        $this->assertGreaterThanOrEqual(4.5, $this->kontras($putih, $this->warna('m600')), 'putih di atas merah awal tombol');
        $this->assertGreaterThanOrEqual(7.0, $this->kontras($this->warna('gelap'), $putih), 'teks gelap di atas permukaan padat');
        $this->assertGreaterThanOrEqual(4.5, $this->kontras($this->warna('redup'), $putih), 'teks redup di atas permukaan padat');
        $this->assertGreaterThanOrEqual(4.5, $this->kontras($this->warna('m800'), '#fee2e2'), 'tautan di kotak merah muda');
        $this->assertGreaterThanOrEqual(4.5, $this->kontras($this->warna('m800'), '#fff3cd'), 'tautan di kotak kuning');
        $this->assertGreaterThanOrEqual(4.5, $this->kontras('#5b3a00', '#fff3cd'), 'teks di kotak kuning');
    }
}

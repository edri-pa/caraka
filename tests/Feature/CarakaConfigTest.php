<?php

namespace Tests\Feature;

use Tests\TestCase;

class CarakaConfigTest extends TestCase
{
    public function test_berkas_konfigurasi_ada_dan_memuat_kunci_wajib(): void
    {
        $this->assertFileExists(config_path('caraka.php'));

        $konfigurasi = config('caraka');

        $this->assertIsArray($konfigurasi, 'config/caraka.php tidak termuat.');
        $this->assertArrayHasKey('demo_staff_password', $konfigurasi);
        $this->assertArrayHasKey('versi', $konfigurasi);
    }

    public function test_versi_di_konfigurasi_sama_dengan_berkas_version(): void
    {
        $dariBerkas = trim((string) file_get_contents(base_path('VERSION')));

        $this->assertNotSame('', $dariBerkas, 'Berkas VERSION kosong.');
        $this->assertSame($dariBerkas, config('caraka.versi'));
    }
}

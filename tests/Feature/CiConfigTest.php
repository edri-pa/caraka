<?php

namespace Tests\Feature;

use Tests\TestCase;

class CiConfigTest extends TestCase
{
    private function teks(): string
    {
        $path = base_path('.github/workflows/tes.yml');

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_alur_ci_menjalankan_tes_dengan_postgis_dan_basis_data_testing(): void
    {
        $teks = $this->teks();

        $this->assertStringContainsString('run: php artisan test', $teks);
        $this->assertMatchesRegularExpression('/^on:\s*\n\s+push:/m', $teks);
        $this->assertMatchesRegularExpression('/image:\s*postgis\/postgis/', $teks);
        $this->assertMatchesRegularExpression('/DB_DATABASE:\s*testing/', $teks);
        $this->assertMatchesRegularExpression('/DB_CONNECTION:\s*pgsql/', $teks);
    }

    public function test_alur_ci_memakai_izin_minimal_dan_tanpa_rahasia(): void
    {
        $teks = $this->teks();

        $this->assertMatchesRegularExpression('/^permissions:\s*\n\s+contents:\s*read\s*$/m', $teks);
        $this->assertStringNotContainsString('pull_request_target', $teks);
        $this->assertStringNotContainsString('secrets.', $teks);
    }

    public function test_alur_ci_punya_batas_waktu_dan_pembatalan_otomatis(): void
    {
        $teks = $this->teks();

        preg_match_all('/timeout-minutes:\s*(\d+)/', $teks, $cocok);

        $this->assertGreaterThanOrEqual(2, count($cocok[1]), 'Setiap pekerjaan harus punya timeout-minutes.');

        foreach ($cocok[1] as $menit) {
            $this->assertLessThanOrEqual(30, (int) $menit);
        }

        $this->assertMatchesRegularExpression('/cancel-in-progress:\s*true/', $teks);
    }

    public function test_semua_aksi_memakai_versi_tertentu(): void
    {
        preg_match_all('/^\s*(?:-\s*)?uses:\s*(\S+)/m', $this->teks(), $cocok);

        $this->assertNotEmpty($cocok[1]);

        foreach ($cocok[1] as $aksi) {
            $this->assertStringContainsString('@', $aksi, "Aksi tanpa versi: {$aksi}");
        }
    }

    public function test_audit_composer_memeriksa_berkas_kunci_tanpa_memasang_paket(): void
    {
        $this->assertMatchesRegularExpression('/run:\s*composer audit\s+--locked/', $this->teks());
    }

    public function test_audit_javascript_tetap_berjalan_walau_audit_composer_gagal(): void
    {
        $this->assertMatchesRegularExpression('/if:\s*\$\{\{\s*always\(\)\s*\}\}\s*\n\s*run:\s*npm audit/', $this->teks());
    }

    public function test_dependabot_memantau_aksi_github(): void
    {
        $path = base_path('.github/dependabot.yml');

        $this->assertFileExists($path);
        $this->assertStringContainsString('github-actions', file_get_contents($path));
    }
}

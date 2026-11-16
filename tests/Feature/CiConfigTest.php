<?php

namespace Tests\Feature;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class CiConfigTest extends TestCase
{
    private function teks(): string
    {
        $path = base_path('.github/workflows/tes.yml');

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function yaml(): array
    {
        if (! class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml tidak tersedia.');
        }

        return Yaml::parse($this->teks());
    }

    public function test_alur_ci_menjalankan_tes_dengan_postgis_dan_basis_data_testing(): void
    {
        $teks = $this->teks();
        $y = $this->yaml();

        $this->assertStringContainsString('php artisan test', $teks);
        $this->assertStringContainsString('push:', $teks);
        $this->assertStringContainsString('postgis/postgis', $y['jobs']['tes']['services']['postgres']['image']);
        $this->assertSame('testing', $y['jobs']['tes']['env']['DB_DATABASE']);
        $this->assertSame('pgsql', $y['jobs']['tes']['env']['DB_CONNECTION']);
    }

    public function test_alur_ci_memakai_izin_minimal_dan_tanpa_rahasia(): void
    {
        $teks = $this->teks();
        $y = $this->yaml();

        $this->assertSame(['contents' => 'read'], $y['permissions']);
        $this->assertStringNotContainsString('pull_request_target', $teks);
        $this->assertStringNotContainsString('secrets.', $teks);
    }

    public function test_alur_ci_punya_batas_waktu_dan_pembatalan_otomatis(): void
    {
        $y = $this->yaml();

        $this->assertLessThanOrEqual(30, $y['jobs']['tes']['timeout-minutes']);
        $this->assertLessThanOrEqual(30, $y['jobs']['audit']['timeout-minutes']);
        $this->assertTrue($y['concurrency']['cancel-in-progress']);
    }

    public function test_semua_aksi_memakai_versi_tertentu(): void
    {
        preg_match_all('/^\s*(?:-\s*)?uses:\s*(\S+)/m', $this->teks(), $cocok);

        $this->assertNotEmpty($cocok[1]);

        foreach ($cocok[1] as $aksi) {
            $this->assertStringContainsString('@', $aksi, "Aksi tanpa versi: {$aksi}");
        }
    }

    public function test_dependabot_memantau_aksi_github(): void
    {
        $path = base_path('.github/dependabot.yml');

        $this->assertFileExists($path);
        $this->assertStringContainsString('github-actions', file_get_contents($path));
    }
}

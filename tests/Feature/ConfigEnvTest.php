<?php

namespace Tests\Feature;

use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConfigEnvTest extends TestCase
{
    use RefreshDatabase;

    private const SANDI = 'sandi-demo-sangat-panjang-12345';

    private const POLA = '/(?<![\w>:$.])env\s*\(/';

    public function test_tidak_ada_env_langsung_di_luar_berkas_konfigurasi(): void
    {
        $ditemukan = [];

        foreach (['app', 'bootstrap', 'database', 'routes', 'resources/views'] as $folder) {
            $dir = base_path($folder);

            if (! is_dir($dir)) {
                continue;
            }

            $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

            foreach ($iter as $berkas) {
                if (! $berkas->isFile() || ! str_ends_with($berkas->getFilename(), '.php')) {
                    continue;
                }

                if (str_contains($berkas->getPathname(), '/bootstrap/cache/')) {
                    continue;
                }

                foreach (file($berkas->getPathname()) as $no => $baris) {
                    if (preg_match(self::POLA, $baris) === 1) {
                        $ditemukan[] = str_replace(base_path().'/', '', $berkas->getPathname()).':'.($no + 1);
                    }
                }
            }
        }

        $this->assertSame([], $ditemukan, "env() dipakai langsung (pakai config()):\n".implode("\n", $ditemukan));
    }

    public function test_pola_pemindai_membedakan_panggilan_env_dari_teks_biasa(): void
    {
        foreach ([
            "\$x = env('A');",
            "return \\env('A');",
            "foo(env('A'))",
            "\$x = env ('A');",
        ] as $baris) {
            $this->assertSame(1, preg_match(self::POLA, $baris), $baris);
        }

        foreach ([
            "throw new RuntimeException('Isi .env (minimal 12 karakter).');",
            "\$this->env('A');",
            "app()->environment('production');",
            "Env::get('A');",
            "\$x = config('caraka.versi');",
            "// lihat berkas .env (contoh)",
        ] as $baris) {
            $this->assertSame(0, preg_match(self::POLA, $baris), $baris);
        }
    }

    public function test_seeder_membaca_kata_sandi_dari_konfigurasi(): void
    {
        DB::table('agencies')->insert([
            'name' => 'Instansi Uji', 'type' => 'medis', 'is_demo' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        config(['caraka.demo_staff_password' => self::SANDI]);

        $this->seed(UserSeeder::class);

        $peran = DB::table('users')->pluck('role', 'email')->all();

        $this->assertSame([
            'admin@caraka.test' => 'admin',
            'dispatcher@caraka.test' => 'dispatcher',
            'responder@caraka.test' => 'responder',
        ], collect($peran)->sortKeys()->all());

        $this->assertTrue(Hash::check(self::SANDI, DB::table('users')->where('email', 'dispatcher@caraka.test')->value('password')));
    }

    public function test_seeder_menolak_kata_sandi_kosong(): void
    {
        config(['caraka.demo_staff_password' => null]);

        $this->expectException(\Throwable::class);

        $this->seed(UserSeeder::class);
    }

    private function amankan(): void
    {
        config([
            'app.debug' => false,
            'database.connections.pgsql.password' => 'sandi-uji-yang-panjang-1',
        ]);
    }

    public function test_keamanan_periksa_memperingatkan_bila_kata_sandi_demo_terisi(): void
    {
        $this->amankan();
        config(['caraka.demo_staff_password' => 'sesuatu']);

        $this->artisan('keamanan:periksa')
            ->expectsOutputToContain('DEMO_STAFF_PASSWORD')
            ->assertExitCode(0);
    }

    public function test_keamanan_periksa_tidak_memperingatkan_bila_kata_sandi_demo_kosong(): void
    {
        $this->amankan();
        config(['caraka.demo_staff_password' => null]);

        $this->artisan('keamanan:periksa')
            ->doesntExpectOutputToContain('DEMO_STAFF_PASSWORD')
            ->assertExitCode(0);
    }
}

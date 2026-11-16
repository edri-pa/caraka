<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AppVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppVersionTest extends TestCase
{
    use RefreshDatabase;

    private const SHA = '0123456789abcdef0123456789abcdef01234567';

    private array $folder = [];

    protected function tearDown(): void
    {
        foreach ($this->folder as $dir) {
            File::deleteDirectory($dir);
        }

        parent::tearDown();
    }

    private function gitSementara(array $berkas): string
    {
        $dir = sys_get_temp_dir().'/caraka-git-'.uniqid();
        mkdir($dir.'/.git/refs/heads', 0777, true);

        foreach ($berkas as $nama => $isi) {
            file_put_contents($dir.'/.git/'.$nama, $isi);
        }

        $this->folder[] = $dir;

        return $dir;
    }

    private function versi(): string
    {
        return trim((string) file_get_contents(base_path('VERSION')));
    }

    public function test_berkas_versi_valid(): void
    {
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+(-[A-Za-z0-9.]+)?$/', $this->versi());
        $this->assertSame($this->versi(), config('caraka.versi'));
    }

    public function test_changelog_memuat_versi_yang_berjalan(): void
    {
        $dasar = preg_replace('/-.*/', '', $this->versi());
        $changelog = file_get_contents(base_path('CHANGELOG.md'));

        $this->assertStringContainsString($dasar, $changelog);
        $this->assertStringContainsString('## [', $changelog);
    }

    public function test_label_versi_dan_komit_berformat_benar(): void
    {
        $label = app(AppVersion::class)->label(true);

        $this->assertMatchesRegularExpression('/^v\d+\.\d+\.\d+(-[a-z0-9.]+)?( \([0-9a-f]{7}\))?$/', $label);
        $this->assertSame('v'.$this->versi(), app(AppVersion::class)->label(false));
    }

    public function test_komit_dibaca_dari_ref_cabang(): void
    {
        $dir = $this->gitSementara(['HEAD' => "ref: refs/heads/main\n", 'refs/heads/main' => self::SHA."\n"]);

        $this->assertSame('0123456', (new AppVersion($dir))->commit());
    }

    public function test_komit_dibaca_dari_kepala_terlepas(): void
    {
        $dir = $this->gitSementara(['HEAD' => self::SHA."\n"]);

        $this->assertSame('0123456', (new AppVersion($dir))->commit());
    }

    public function test_komit_dibaca_dari_packed_refs(): void
    {
        $dir = $this->gitSementara([
            'HEAD' => "ref: refs/heads/main\n",
            'packed-refs' => "# pack-refs with: peeled fully-peeled sorted\n".self::SHA." refs/heads/main\n",
        ]);

        $this->assertSame('0123456', (new AppVersion($dir))->commit());
    }

    public function test_komit_tidak_tersedia_atau_tidak_valid_menghasilkan_null(): void
    {
        $kosong = sys_get_temp_dir().'/caraka-kosong-'.uniqid();
        mkdir($kosong);
        $this->folder[] = $kosong;

        $this->assertNull((new AppVersion($kosong))->commit());
        $this->assertNull((new AppVersion($this->gitSementara(['HEAD' => "bukan sha\n"])))->commit());
        $this->assertNull((new AppVersion($this->gitSementara(['HEAD' => "ref: ../../etc/passwd\n"])))->commit());
    }

    public function test_versi_tampil_di_halaman_publik_tanpa_komit(): void
    {
        $html = $this->get('/')->assertOk()->assertSee('Caraka v'.$this->versi())->getContent();

        $this->assertDoesNotMatchRegularExpression('/Caraka v[^<]*\([0-9a-f]{7}\)/', $html);
    }

    public function test_versi_dan_komit_tampil_di_halaman_petugas(): void
    {
        if (app(AppVersion::class)->commit() === null) {
            $this->markTestSkipped('Folder .git tidak tersedia.');
        }

        $this->actingAs(User::factory()->create(['role' => 'dispatcher']));

        $html = $this->get('/dispatcher/laporan')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/Caraka v[^<]*\([0-9a-f]{7}\)/', $html);
    }

    public function test_demo_periksa_menampilkan_versi(): void
    {
        Http::fake(['*' => Http::response('', 404)]);

        $this->artisan('demo:periksa')
            ->expectsOutputToContain('Versi aplikasi: v')
            ->assertExitCode(1);
    }
}

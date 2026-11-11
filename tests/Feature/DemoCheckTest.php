<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DemoCheckTest extends TestCase
{
    use RefreshDatabase;

    private array $folder = [];

    protected function tearDown(): void
    {
        foreach ($this->folder as $dir) {
            File::deleteDirectory($dir);
        }

        parent::tearDown();
    }

    private function publicSementara(bool $manifest = true, bool $hot = false): void
    {
        $dir = sys_get_temp_dir().'/caraka-public-'.uniqid();
        mkdir($dir.'/build', 0777, true);

        if ($manifest) {
            file_put_contents($dir.'/build/manifest.json', '{}');
        }

        if ($hot) {
            file_put_contents($dir.'/hot', 'http://localhost:5173');
        }

        $this->folder[] = $dir;
        app()->usePublicPath($dir);
    }

    private function siapkan(array $tanpa = []): void
    {
        config(['app.debug' => false]);
        Http::fake(['*' => Http::response('', 404)]);

        $agency = DB::table('agencies')->insertGetId([
            'name' => 'Instansi Uji', 'type' => 'medis', 'is_demo' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        if (! in_array('kategori', $tanpa, true)) {
            DB::table('categories')->insert([
                'name' => 'Uji', 'slug' => 'uji', 'base_priority' => 3,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::table('districts')->insert(['name' => 'Kecamatan Uji', 'created_at' => now(), 'updated_at' => now()]);

        User::factory()->create(['role' => 'dispatcher']);
        User::factory()->create([
            'role' => 'responder',
            'agency_id' => in_array('instansi-responder', $tanpa, true) ? null : $agency,
        ]);

        if (! in_array('admin', $tanpa, true)) {
            User::factory()->create(['role' => 'admin']);
        }

        if (! in_array('fasilitas', $tanpa, true)) {
            DB::table('facilities')->insert([
                'name' => 'Fasilitas Nyata', 'type' => 'puskesmas', 'region' => 'Temanggung',
                'location' => DB::raw('ST_SetSRID(ST_MakePoint(110.18, -7.32), 4326)::geography'),
                'is_active' => true, 'is_demo' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->publicSementara();
    }

    public function test_siap_bila_semua_terpenuhi(): void
    {
        $this->siapkan();

        $this->artisan('demo:periksa')
            ->expectsOutputToContain('SIAP.')
            ->doesntExpectOutputToContain('BELUM SIAP')
            ->doesntExpectOutputToContain('DENGAN CATATAN')
            ->assertExitCode(0);
    }

    public function test_belum_siap_bila_responder_tidak_terhubung_ke_instansi(): void
    {
        $this->siapkan(['instansi-responder']);

        $this->artisan('demo:periksa')
            ->expectsOutputToContain('responder yang terhubung ke instansi')
            ->expectsOutputToContain('BELUM SIAP')
            ->assertExitCode(1);
    }

    public function test_reverb_mati_hanya_menjadi_catatan_karena_ada_pengecekan_berkala(): void
    {
        $this->siapkan();
        Http::fake(fn () => throw new ConnectionException('ditolak'));

        $this->artisan('demo:periksa')
            ->expectsOutputToContain('Reverb tidak menjawab')
            ->expectsOutputToContain('SIAP DENGAN CATATAN')
            ->assertExitCode(0);
    }

    public function test_tanpa_fasilitas_nyata_menjadi_catatan(): void
    {
        $this->siapkan(['fasilitas']);

        $this->artisan('demo:periksa')
            ->expectsOutputToContain('fasilitas nyata')
            ->assertExitCode(0);
    }

    public function test_hot_yang_tertinggal_bersama_build_menjadi_catatan(): void
    {
        $this->siapkan();
        $this->publicSementara(true, true);

        $this->artisan('demo:periksa')
            ->expectsOutputToContain('public/hot')
            ->assertExitCode(0);
    }

    public function test_mode_pengembangan_tanpa_build_hanya_catatan(): void
    {
        $this->siapkan();
        $this->publicSementara(false, true);

        $this->artisan('demo:periksa')
            ->expectsOutputToContain('Mode pengembangan')
            ->assertExitCode(0);
    }

    public function test_belum_siap_bila_aset_belum_dibangun_dan_vite_mati(): void
    {
        $this->siapkan();
        $this->publicSementara(false, false);

        $this->artisan('demo:periksa')
            ->expectsOutputToContain('Aset belum dibangun')
            ->assertExitCode(1);
    }

    public function test_laporan_lama_dilaporkan_dengan_petunjuk_ekspor_dan_reset(): void
    {
        $this->siapkan();

        DB::table('incidents')->insert([
            'category_id' => DB::table('categories')->value('id'),
            'reference' => 'ABCDE23456',
            'location' => null,
            'location_source' => 'manual',
            'status' => 'baru',
            'reported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('demo:periksa')
            ->expectsOutputToContain('1 laporan lama')
            ->expectsOutputToContain('gladi:ekspor')
            ->assertExitCode(0);
    }

    public function test_belum_siap_tanpa_kategori(): void
    {
        $this->siapkan(['kategori']);

        $this->artisan('demo:periksa')
            ->expectsOutputToContain('Kategori kosong')
            ->assertExitCode(1);
    }

    public function test_belum_siap_tanpa_akun_admin(): void
    {
        $this->siapkan(['admin']);

        $this->artisan('demo:periksa')
            ->expectsOutputToContain('akun admin')
            ->assertExitCode(1);
    }
}

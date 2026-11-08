<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScreenshotFindingsTest extends TestCase
{
    use RefreshDatabase;

    private function buatLaporan(array $ubah = []): int
    {
        $kategori = DB::table('categories')->where('slug', 'uji')->value('id')
            ?? DB::table('categories')->insertGetId([
                'name' => 'Uji', 'slug' => 'uji', 'base_priority' => 3,
                'created_at' => now(), 'updated_at' => now(),
            ]);

        return DB::table('incidents')->insertGetId(array_merge([
            'category_id' => $kategori,
            'location' => null,
            'location_source' => 'manual',
            'reference' => strtoupper(substr(md5(uniqid('', true)), 0, 10)),
            'status' => 'baru',
            'trust_score' => 40,
            'priority_score' => 3,
            'reported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $ubah));
    }

    private function masukSebagaiDispatcher(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'dispatcher']));
    }

    public function test_daftar_di_halaman_petugas_berlatar_gelap_berwarna_terang(): void
    {
        $css = file_get_contents(public_path('css/caraka.css'));

        $this->assertStringContainsString('body.caraka.staf main > ul', $css);
    }

    public function test_lebar_konten_dashboard_dibatasi(): void
    {
        $css = file_get_contents(public_path('css/caraka.css'));

        $this->assertStringContainsString(':not(main){max-width:1240px', $css);
    }

    public function test_detail_memakai_istilah_skor_sinyal(): void
    {
        $id = $this->buatLaporan();
        $this->masukSebagaiDispatcher();

        $this->get("/staf/laporan/{$id}")
            ->assertOk()
            ->assertSee('Skor sinyal')
            ->assertSee('bukan kebenaran isi laporan')
            ->assertDontSee('Skor kepercayaan');
    }

    public function test_dashboard_tidak_menduplikasi_tautan_statistik(): void
    {
        $this->masukSebagaiDispatcher();

        $html = $this->get('/dispatcher/laporan')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '/statistik"'));
    }

    public function test_laporan_berumur_berhari_hari_ditampilkan_dalam_hari(): void
    {
        $this->buatLaporan([
            'status' => 'ditugaskan',
            'reported_at' => now()->subSeconds(626400)->format('Y-m-d H:i:s'),
        ]);
        $this->masukSebagaiDispatcher();

        $this->get('/dispatcher/laporan?status=semua')
            ->assertOk()
            ->assertSee('berjalan 7 hari 6 jam')
            ->assertDontSee('174 jam');
    }
}

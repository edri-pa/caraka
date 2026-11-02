<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrialFindingsTest extends TestCase
{
    use RefreshDatabase;

    private int $kategoriId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kategoriId = DB::table('categories')->insertGetId([
            'name' => 'Kategori Uji',
            'slug' => 'uji',
            'base_priority' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::factory()->create(['role' => 'dispatcher']));
    }

    private function buatLaporan(string $status, int $menitLalu, string $referensi = 'ABCDE23456'): void
    {
        DB::table('incidents')->insert([
            'category_id' => $this->kategoriId,
            'location' => null,
            'location_source' => 'manual',
            'reference' => $referensi,
            'status' => $status,
            'trust_score' => 90,
            'priority_score' => 3,
            'reported_at' => now()->subMinutes($menitLalu)->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_laporan_baru_menampilkan_menunggu_dengan_durasi_terformat(): void
    {
        $this->buatLaporan('baru', 1530);

        $this->get('/dispatcher/laporan?status=semua')
            ->assertOk()
            ->assertSee('menunggu 25 jam')
            ->assertDontSee('1530 menit');
    }

    public function test_laporan_berjalan_menampilkan_berjalan_bukan_menunggu(): void
    {
        $this->buatLaporan('ditugaskan', 90);

        $this->get('/dispatcher/laporan?status=semua')
            ->assertOk()
            ->assertSee('berjalan 1 jam')
            ->assertDontSee('menunggu');
    }

    public function test_laporan_ditutup_tidak_menampilkan_hitungan_waktu(): void
    {
        $this->buatLaporan('tidak_terbukti', 1530);

        $this->get('/dispatcher/laporan?status=semua')
            ->assertOk()
            ->assertSee('ditutup')
            ->assertDontSee('menunggu');
    }

    public function test_kolom_skor_sinyal_menjelaskan_bahwa_isi_laporan_tidak_dinilai(): void
    {
        $this->buatLaporan('baru', 5);

        $this->get('/dispatcher/laporan')
            ->assertOk()
            ->assertSee('Skor sinyal')
            ->assertSee('bukan kebenaran isi laporan')
            ->assertDontSee('<th>Kepercayaan</th>', false);
    }

    public function test_penunjuk_real_time_punya_batas_waktu(): void
    {
        $this->get('/dispatcher/laporan')
            ->assertOk()
            ->assertSee('Real-time belum tersambung');
    }

    public function test_peringatan_darurat_konsisten_tanpa_rujukan_112(): void
    {
        $this->buatLaporan('baru', 5);

        foreach (['/', '/lapor', '/sos', '/lapor/ABCDE23456'] as $alamat) {
            $this->get($alamat)
                ->assertOk()
                ->assertSee('PSC 119')
                ->assertDontSee('resmi (112)');
        }
    }
}

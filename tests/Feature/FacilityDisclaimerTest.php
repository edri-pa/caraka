<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FacilityDisclaimerTest extends TestCase
{
    use RefreshDatabase;

    private function siapkan(bool $gps, ?string $dicek = '2026-10-18'): int
    {
        $kategori = DB::table('categories')->insertGetId([
            'name' => 'Medis',
            'slug' => 'medis',
            'base_priority' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('facilities')->insert([
            'name' => 'RS Uji',
            'type' => 'rumah_sakit',
            'region' => 'UJI',
            'location' => DB::raw('ST_SetSRID(ST_MakePoint(110.190, -7.320), 4326)::geography'),
            'is_24h' => true,
            'has_igd' => true,
            'is_active' => true,
            'is_demo' => true,
            'source' => 'Uji tes',
            'verified_at' => $dicek,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('incidents')->insertGetId([
            'category_id' => $kategori,
            'location' => $gps ? DB::raw('ST_SetSRID(ST_MakePoint(110.182, -7.320), 4326)::geography') : null,
            'location_source' => $gps ? 'gps' : 'manual',
            'reference' => strtoupper(substr(md5(uniqid('', true)), 0, 10)),
            'status' => 'baru',
            'reported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function masukSebagaiDispatcher(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'dispatcher']));
    }

    public function test_catatan_data_belum_diverifikasi_tampil_di_bagian_peta(): void
    {
        $id = $this->siapkan(true);
        $this->masukSebagaiDispatcher();

        $this->get("/staf/laporan/{$id}")
            ->assertOk()
            ->assertSee('belum diverifikasi instansi');
    }

    public function test_tanggal_pengecekan_fasilitas_tampil(): void
    {
        $id = $this->siapkan(true, '2026-10-18');
        $this->masukSebagaiDispatcher();

        $this->get("/staf/laporan/{$id}")->assertSee('dicek 18-10-2026');
    }

    public function test_laporan_manual_tidak_menampilkan_catatan_fasilitas(): void
    {
        $id = $this->siapkan(false);
        $this->masukSebagaiDispatcher();

        $this->get("/staf/laporan/{$id}")
            ->assertOk()
            ->assertDontSee('belum diverifikasi instansi');
    }
}

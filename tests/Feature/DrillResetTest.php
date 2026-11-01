<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DrillResetTest extends TestCase
{
    use RefreshDatabase;

    private function isiData(): void
    {
        $kategori = DB::table('categories')->insertGetId([
            'name' => 'Uji', 'slug' => 'uji', 'base_priority' => 3,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $instansi = DB::table('agencies')->insertGetId([
            'name' => 'Instansi Uji', 'type' => 'medis', 'is_demo' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('districts')->insert(['name' => 'Kecamatan Uji', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('facilities')->insert([
            'name' => 'Fasilitas Uji', 'type' => 'puskesmas', 'region' => 'UJI',
            'location' => DB::raw('ST_SetSRID(ST_MakePoint(110.18, -7.32), 4326)::geography'),
            'is_active' => true, 'is_demo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        User::factory()->create();

        $id = DB::table('incidents')->insertGetId([
            'category_id' => $kategori,
            'reference' => 'ABCDE23456',
            'location' => null,
            'location_source' => 'manual',
            'status' => 'baru',
            'reported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('incident_logs')->insert([
            'incident_id' => $id, 'action' => 'dilaporkan', 'to_status' => 'baru', 'created_at' => now(),
        ]);
        DB::table('incident_agencies')->insert([
            'incident_id' => $id, 'agency_id' => $instansi, 'role' => 'utama', 'assigned_at' => now(),
        ]);
    }

    public function test_tanpa_opsi_yakin_hanya_menampilkan_jumlah(): void
    {
        $this->isiData();

        $this->artisan('gladi:reset')
            ->expectsOutputToContain('Laporan: 1')
            ->assertExitCode(0);

        $this->assertDatabaseCount('incidents', 1);
        $this->assertDatabaseCount('incident_logs', 1);
    }

    public function test_dengan_yakin_mengosongkan_laporan_dan_menyisakan_data_acuan(): void
    {
        $this->isiData();

        $this->artisan('gladi:reset', ['--yakin' => true])->assertExitCode(0);

        $this->assertDatabaseCount('incidents', 0);
        $this->assertDatabaseCount('incident_logs', 0);
        $this->assertDatabaseCount('incident_agencies', 0);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('facilities', 1);
        $this->assertDatabaseCount('agencies', 1);
        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseCount('districts', 1);
    }

    public function test_menolak_di_lingkungan_production_tanpa_paksa(): void
    {
        $this->isiData();
        app()->detectEnvironment(fn () => 'production');

        $this->artisan('gladi:reset', ['--yakin' => true])->assertExitCode(1);

        $this->assertDatabaseCount('incidents', 1);
    }

    public function test_opsi_paksa_mengizinkan_di_production(): void
    {
        $this->isiData();
        app()->detectEnvironment(fn () => 'production');

        $this->artisan('gladi:reset', ['--yakin' => true, '--paksa' => true])->assertExitCode(0);

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_pada_database_kosong_tidak_menimbulkan_galat(): void
    {
        $this->artisan('gladi:reset', ['--yakin' => true])
            ->expectsOutputToContain('Laporan: 0')
            ->assertExitCode(0);
    }
}

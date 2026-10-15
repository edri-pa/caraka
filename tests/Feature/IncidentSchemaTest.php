<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IncidentSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function makeCategory(): int
    {
        return DB::table('categories')->insertGetId([
            'name' => 'Uji',
            'slug' => 'uji',
            'base_priority' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertIncident(int $categoryId, string $reference): void
    {
        DB::table('incidents')->insert([
            'category_id' => $categoryId,
            'location' => null,
            'location_source' => 'manual',
            'reference' => $reference,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_kolom_pelaporan_ada(): void
    {
        $this->assertTrue(Schema::hasColumns('incidents', ['reference', 'reporter_phone', 'location_source']));
    }

    public function test_laporan_manual_tanpa_koordinat_bisa_disimpan(): void
    {
        $this->insertIncident($this->makeCategory(), 'ABCDE23456');

        $this->assertDatabaseHas('incidents', [
            'reference' => 'ABCDE23456',
            'location_source' => 'manual',
        ]);
    }

    public function test_nomor_referensi_harus_unik(): void
    {
        $kategori = $this->makeCategory();
        $this->insertIncident($kategori, 'ABCDE23456');

        $this->expectException(QueryException::class);

        $this->insertIncident($kategori, 'ABCDE23456');
    }
}

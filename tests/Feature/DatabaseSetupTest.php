<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_tes_memakai_database_testing(): void
    {
        $this->assertSame('testing', DB::connection()->getDatabaseName());
    }

    public function test_postgis_aktif_di_database_testing(): void
    {
        $row = DB::selectOne('select postgis_version() as versi');

        $this->assertNotEmpty($row->versi);
    }
}

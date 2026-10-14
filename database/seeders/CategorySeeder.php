<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $parents = [
            ['slug' => 'medis',                  'name' => 'Medis',                  'base_priority' => 5, 'default_agency_type' => 'medis'],
            ['slug' => 'kecelakaan-lalu-lintas', 'name' => 'Kecelakaan lalu lintas', 'base_priority' => 4, 'default_agency_type' => 'polisi'],
            ['slug' => 'kebakaran',              'name' => 'Kebakaran',              'base_priority' => 5, 'default_agency_type' => 'damkar'],
            ['slug' => 'bencana-alam',           'name' => 'Bencana alam',           'base_priority' => 4, 'default_agency_type' => 'bpbd'],
            ['slug' => 'kriminal',               'name' => 'Kriminal',               'base_priority' => 3, 'default_agency_type' => 'polisi'],
            ['slug' => 'lainnya',                'name' => 'Lainnya',                'base_priority' => 2, 'default_agency_type' => null],
        ];

        foreach ($parents as $row) {
            $this->upsert($row + ['parent_id' => null]);
        }

        $bencanaId = DB::table('categories')->where('slug', 'bencana-alam')->value('id');

        $children = [
            ['slug' => 'longsor',    'name' => 'Tanah longsor', 'base_priority' => 4, 'default_agency_type' => 'bpbd'],
            ['slug' => 'banjir',     'name' => 'Banjir',        'base_priority' => 4, 'default_agency_type' => 'bpbd'],
            ['slug' => 'gunung-api', 'name' => 'Gunung api',    'base_priority' => 5, 'default_agency_type' => 'bpbd'],
        ];

        foreach ($children as $row) {
            $this->upsert($row + ['parent_id' => $bencanaId]);
        }
    }

    private function upsert(array $row): void
    {
        DB::table('categories')->updateOrInsert(
            ['slug' => $row['slug']],
            $row + ['created_at' => now(), 'updated_at' => now()]
        );
    }
}
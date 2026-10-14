<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DistrictSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $districts = [
            ['name' => 'Bansari',     'code' => '33.23.16'],
            ['name' => 'Bejen',       'code' => '33.23.18'],
            ['name' => 'Bulu',        'code' => '33.23.01'],
            ['name' => 'Candiroto',   'code' => '33.23.12'],
            ['name' => 'Gemawang',    'code' => '33.23.20'],
            ['name' => 'Jumo',        'code' => '33.23.10'],
            ['name' => 'Kaloran',     'code' => '33.23.05'],
            ['name' => 'Kandangan',   'code' => '33.23.06'],
            ['name' => 'Kedu',        'code' => '33.23.07'],
            ['name' => 'Kledung',     'code' => '33.23.17'],
            ['name' => 'Kranggan',    'code' => '33.23.13'],
            ['name' => 'Ngadirejo',   'code' => '33.23.09'],
            ['name' => 'Parakan',     'code' => '33.23.08'],
            ['name' => 'Pringsurat',  'code' => '33.23.04'],
            ['name' => 'Selopampang', 'code' => '33.23.15'],
            ['name' => 'Temanggung',  'code' => '33.23.03'],
            ['name' => 'Tembarak',    'code' => '33.23.02'],
            ['name' => 'Tlogomulyo',  'code' => '33.23.14'],
            ['name' => 'Tretep',      'code' => '33.23.11'],
            ['name' => 'Wonoboyo',    'code' => '33.23.19'],
        ];
        foreach ($districts as $district) {
            DB::table('districts')->updateOrInsert(
                ['name' => $district['name']],
                [
                    'code' => $district['code'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}

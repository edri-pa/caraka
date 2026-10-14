<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AgencySeeder extends Seeder
{
    public function run(): void
    {
        $agencies = [
            ['name' => 'Layanan Medis Temanggung',     'type' => 'medis',  'phone' => 'DEMO-0001'],
            ['name' => 'Pemadam Kebakaran Temanggung', 'type' => 'damkar', 'phone' => 'DEMO-0002'],
            ['name' => 'Kepolisian Temanggung',        'type' => 'polisi', 'phone' => 'DEMO-0003'],
            ['name' => 'BPBD Kabupaten Temanggung',    'type' => 'bpbd',   'phone' => 'DEMO-0004'],
            ['name' => 'Tim SAR Temanggung',           'type' => 'sar',    'phone' => 'DEMO-0005'],
        ];

        foreach ($agencies as $agency) {
            DB::table('agencies')->updateOrInsert(
                ['name' => $agency['name']],
                $agency + ['is_demo' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
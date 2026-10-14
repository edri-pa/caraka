<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FacilityTestSeeder extends Seeder
{
    public function run(): void
    {
        $agency = fn (string $type) => DB::table('agencies')->where('type', $type)->value('id');

        // [nama, jenis, tipe instansi, bujur, lintang, 24 jam, ada IGD]
        $rows = [
            ['UJI Puskesmas A',  'puskesmas',   'medis',  110.180, -7.320, false, false],
            ['UJI RS B',         'rumah_sakit', 'medis',  110.190, -7.320, true,  true],
            ['UJI Pos Damkar C', 'pos_damkar',  'damkar', 110.180, -7.340, true,  false],
            ['UJI Polsek D',     'polsek',      'polisi', 110.220, -7.320, true,  false],
            ['UJI RS E',         'rumah_sakit', 'medis',  110.100, -7.250, true,  true],
            ['UJI Luar Wilayah', 'rumah_sakit', 'medis',  110.500, -7.000, true,  true],
        ];

        foreach ($rows as [$name, $type, $agencyType, $lng, $lat, $is24h, $hasIgd]) {
            DB::table('facilities')->updateOrInsert(
                ['name' => $name],
                [
                    'agency_id'  => $agency($agencyType),
                    'type'       => $type,
                    'region'     => 'UJI',
                    'location'   => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)::geography', $lng, $lat)),
                    'phone'      => 'DEMO-UJI',
                    'is_24h'     => $is24h,
                    'has_igd'    => $hasIgd,
                    'is_active'  => true,
                    'is_demo'    => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
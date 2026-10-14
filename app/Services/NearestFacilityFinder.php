<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NearestFacilityFinder
{
    /**
     * Fasilitas aktif terdekat dari sebuah titik, diurutkan menurut jarak garis lurus (meter).
     * Hasilnya hanya SARAN. Jarak rute sebenarnya bisa jauh lebih panjang di medan pegunungan.
     */
    public function find(
        float $lng,
        float $lat,
        array $types = [],
        int $radiusMeters = 15000,
        int $limit = 5,
        bool $requireIgd = false,
    ): Collection {
        $query = DB::table('facilities')
            ->selectRaw(
                'id, name, type, region, phone, is_24h, has_igd, is_demo,
                 ROUND(ST_Distance(location, ST_SetSRID(ST_MakePoint(CAST(? AS double precision), CAST(? AS double precision)), 4326)::geography)) AS distance_m',
                [$lng, $lat]
            )
            ->where('is_active', true)
            ->whereRaw(
                'ST_DWithin(location, ST_SetSRID(ST_MakePoint(CAST(? AS double precision), CAST(? AS double precision)), 4326)::geography, CAST(? AS double precision))',
                [$lng, $lat, $radiusMeters]
            )
            ->orderBy('distance_m')
            ->limit($limit);

        if ($types !== []) {
            $query->whereIn('type', $types);
        }

        if ($requireIgd) {
            $query->where('has_igd', true);
        }

        return $query->get();
    }
}

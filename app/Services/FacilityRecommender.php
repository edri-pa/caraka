<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class FacilityRecommender
{
    public function __construct(private NearestFacilityFinder $finder)
    {
    }

    /**
     * Kelompok fasilitas yang relevan (DRAF, perlu divalidasi praktisi).
     * Sejalan dengan aturan saran instansi: layanan medis ikut ditampilkan
     * kecuali dipastikan tidak ada korban luka.
     *
     * @return array<int, array{title: string, types: array<int, string>, require_igd: bool, radius_m: int}>
     */
    public function groupsFor(?string $rootSlug, ?bool $hasCasualty): array
    {
        $igd = ['title' => 'Rumah sakit dengan IGD terdekat', 'types' => ['rumah_sakit'], 'require_igd' => true, 'radius_m' => 30000];
        $kesehatan = ['title' => 'Puskesmas dan klinik terdekat', 'types' => ['puskesmas', 'klinik'], 'require_igd' => false, 'radius_m' => 15000];
        $damkar = ['title' => 'Pos pemadam terdekat', 'types' => ['pos_damkar'], 'require_igd' => false, 'radius_m' => 15000];
        $polisi = ['title' => 'Kantor polisi terdekat', 'types' => ['polsek', 'polres'], 'require_igd' => false, 'radius_m' => 20000];
        $bpbd = ['title' => 'Kantor BPBD', 'types' => ['kantor_bpbd'], 'require_igd' => false, 'radius_m' => 50000];

        return match ($rootSlug) {
            'medis' => [$igd, $kesehatan],
            'kecelakaan-lalu-lintas' => $hasCasualty === false ? [$polisi] : [$igd, $kesehatan, $polisi],
            'kebakaran' => $hasCasualty === false ? [$damkar] : [$damkar, $igd],
            'bencana-alam' => $hasCasualty === false ? [$bpbd] : [$bpbd, $igd],
            'kriminal' => $hasCasualty === true ? [$polisi, $igd] : [$polisi],
            default => [],
        };
    }

    /**
     * @return array{map: array<string, mixed>|null, groups: array<int, array<string, mixed>>}
     */
    public function forIncident(int $incidentId): array
    {
        $incident = DB::table('incidents')
            ->join('categories', 'categories.id', '=', 'incidents.category_id')
            ->leftJoin('categories as parent', 'parent.id', '=', 'categories.parent_id')
            ->where('incidents.id', $incidentId)
            ->selectRaw(
                'incidents.has_casualty, incidents.location_accuracy_m, categories.slug, parent.slug as parent_slug,
                 ST_Y(incidents.location::geometry) as lat, ST_X(incidents.location::geometry) as lng'
            )
            ->first();

        if ($incident === null || $incident->lat === null || $incident->lng === null) {
            return ['map' => null, 'groups' => []];
        }

        $lat = (float) $incident->lat;
        $lng = (float) $incident->lng;
        $groups = [];
        $points = [];

        $rules = $this->groupsFor($incident->parent_slug ?? $incident->slug, $this->toBool($incident->has_casualty));

        foreach ($rules as $group) {
            $items = $this->finder
                ->find($lng, $lat, $group['types'], $group['radius_m'], 3, $group['require_igd'])
                ->map(fn ($f) => [
                    'id' => (int) $f->id,
                    'name' => $f->name,
                    'type' => $f->type,
                    'distance_m' => (int) round((float) $f->distance_m),
                    'is_24h' => (bool) $f->is_24h,
                    'has_igd' => (bool) $f->has_igd,
                    'phone' => $f->phone,
                    'is_demo' => (bool) $f->is_demo,
                    'lat' => (float) $f->lat,
                    'lng' => (float) $f->lng,
                    'source' => $f->source,
                    'verified_at' => $f->verified_at,
                ])
                ->all();

            $groups[] = [
                'title' => $group['title'],
                'radius_km' => (int) round($group['radius_m'] / 1000),
                'items' => $items,
            ];

            foreach ($items as $item) {
                $points[$item['id']] = $item;
            }
        }

        return [
            'map' => [
                'kejadian' => [
                    'lat' => $lat,
                    'lng' => $lng,
                    'akurasi' => $incident->location_accuracy_m !== null ? (int) round((float) $incident->location_accuracy_m) : null,
                ],
                'fasilitas' => array_values(array_map(fn ($p) => [
                    'nama' => $p['name'],
                    'jenis' => $p['type'],
                    'jarak' => $p['distance_m'],
                    'lat' => $p['lat'],
                    'lng' => $p['lng'],
                ], $points)),
            ],
            'groups' => $groups,
        ];
    }

    private function toBool(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['t', 'true', '1'], true);
        }

        return (bool) $value;
    }
}

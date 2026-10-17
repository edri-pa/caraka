<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AgencyRoutingSuggester
{
    /**
     * Aturan awal (DRAF): harus divalidasi dengan praktisi (PSC 119, Polres, BPBD).
     * Hanya SARAN. Keputusan tetap pada dispatcher.
     *
     * @return array<int, array{type: string, role: string}>
     */
    public function rulesFor(?string $rootSlug, ?bool $hasCasualty): array
    {
        return match ($rootSlug) {
            'medis' => [$this->item('medis', 'utama')],
            'kecelakaan-lalu-lintas' => match ($hasCasualty) {
                true => [$this->item('medis', 'utama'), $this->item('polisi', 'pendukung')],
                false => [$this->item('polisi', 'utama')],
                null => [$this->item('polisi', 'utama'), $this->item('medis', 'pendukung')],
            },
            'kebakaran' => $hasCasualty === false
                ? [$this->item('damkar', 'utama')]
                : [$this->item('damkar', 'utama'), $this->item('medis', 'pendukung')],
            'bencana-alam' => $hasCasualty === false
                ? [$this->item('bpbd', 'utama')]
                : [$this->item('bpbd', 'utama'), $this->item('medis', 'pendukung')],
            'kriminal' => $hasCasualty === true
                ? [$this->item('polisi', 'utama'), $this->item('medis', 'pendukung')]
                : [$this->item('polisi', 'utama')],
            default => [],
        };
    }

    /**
     * Saran untuk satu laporan: instansi yang belum ditugaskan, dan tidak pernah
     * menawarkan instansi utama kedua.
     *
     * @return array<int, array{agency_id: int, name: string, type: string, role: string}>
     */
    public function suggest(int $incidentId): array
    {
        $incident = DB::table('incidents')
            ->join('categories', 'categories.id', '=', 'incidents.category_id')
            ->leftJoin('categories as parent', 'parent.id', '=', 'categories.parent_id')
            ->where('incidents.id', $incidentId)
            ->first(['incidents.has_casualty', 'categories.slug', 'parent.slug as parent_slug']);

        if ($incident === null) {
            return [];
        }

        $assigned = DB::table('incident_agencies')
            ->where('incident_id', $incidentId)
            ->pluck('role', 'agency_id');

        $hasMain = $assigned->contains('utama');
        $suggestions = [];

        $rules = $this->rulesFor($incident->parent_slug ?? $incident->slug, $this->toBool($incident->has_casualty));

        foreach ($rules as $rule) {
            if ($rule['role'] === 'utama' && $hasMain) {
                continue;
            }

            $agency = DB::table('agencies')
                ->where('type', $rule['type'])
                ->orderBy('id')
                ->first(['id', 'name', 'type']);

            if ($agency === null || $assigned->has($agency->id)) {
                continue;
            }

            $suggestions[] = [
                'agency_id' => (int) $agency->id,
                'name' => $agency->name,
                'type' => $agency->type,
                'role' => $rule['role'],
            ];
        }

        return $suggestions;
    }

    private function item(string $type, string $role): array
    {
        return ['type' => $type, 'role' => $role];
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

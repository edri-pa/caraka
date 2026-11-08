<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class IncidentStatistics
{
    public const SOS_PREFIX = 'LAPORAN SOS';

    public const STAGES = [
        'diverifikasi' => 'Diverifikasi',
        'ditugaskan' => 'Ditugaskan',
        'ditangani' => 'Ditangani',
    ];

    /** Status yang berarti sebuah tahap masih ditunggu. */
    private const WAITING = [
        'diverifikasi' => ['baru'],
        'ditugaskan' => ['baru', 'diverifikasi'],
        'ditangani' => ['baru', 'diverifikasi', 'ditugaskan'],
    ];

    public function summary(?\DateTimeInterface $since = null): array
    {
        $all = DB::table('incidents')
            ->when($since, fn ($q) => $q->where('reported_at', '>=', $since))
            ->get(['id', 'status', 'reported_at', 'location_source', 'location_accuracy_m', 'description', 'flag_reason']);

        $counted = $all->whereNull('flag_reason')->values();
        $ids = $counted->pluck('id')->all();

        $firsts = $ids === []
            ? collect()
            : DB::table('incident_logs')
                ->where('action', 'ubah_status')
                ->whereIn('to_status', array_keys(self::STAGES))
                ->whereIn('incident_id', $ids)
                ->groupBy('incident_id', 'to_status')
                ->selectRaw('incident_id, to_status, min(created_at) as first_at')
                ->get();

        $reportedAt = $counted->pluck('reported_at', 'id');
        $stages = [];

        foreach (self::STAGES as $code => $label) {
            $durations = [];
            $reached = [];

            foreach ($firsts->where('to_status', $code) as $row) {
                $reached[$row->incident_id] = true;
                $seconds = Carbon::parse($row->first_at)->getTimestamp()
                    - Carbon::parse($reportedAt[$row->incident_id])->getTimestamp();

                if ($seconds >= 0) {
                    $durations[] = $seconds;
                }
            }

            $pending = $counted->filter(
                fn ($i) => in_array($i->status, self::WAITING[$code], true) && ! isset($reached[$i->id])
            )->count();

            $stages[$code] = [
                'label' => $label,
                'count' => count($durations),
                'pending' => $pending,
                'median' => $this->median($durations),
                'mean' => $durations === [] ? null : (int) round(array_sum($durations) / count($durations)),
                'min' => $durations === [] ? null : min($durations),
                'max' => $durations === [] ? null : max($durations),
            ];
        }

        $gps = $counted->where('location_source', 'gps');
        $accuracies = $gps->pluck('location_accuracy_m')
            ->filter(fn ($a) => $a !== null)
            ->map(fn ($a) => (float) $a)
            ->values()
            ->all();

        return [
            'total' => $counted->count(),
            'total_semua' => $all->count(),
            'ditandai' => [
                'sengaja_palsu' => $all->where('flag_reason', 'sengaja_palsu')->count(),
                'lainnya' => $all->filter(
                    fn ($i) => $i->flag_reason !== null && $i->flag_reason !== 'sengaja_palsu'
                )->count(),
            ],
            'stages' => $stages,
            'gps' => [
                'dengan_gps' => $gps->count(),
                'manual' => $counted->count() - $gps->count(),
                'median_akurasi' => $this->median($accuracies),
            ],
            'sos' => $counted->filter(
                fn ($i) => str_starts_with((string) $i->description, self::SOS_PREFIX)
            )->count(),
            'status' => $counted->groupBy('status')->map(fn ($g) => $g->count())->all(),
        ];
    }

    public function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        return $n % 2 === 1
            ? (float) $values[$mid]
            : ($values[$mid - 1] + $values[$mid]) / 2;
    }

    public function formatDuration(?float $seconds): string
    {
        if ($seconds === null) {
            return '-';
        }

        $seconds = (int) round($seconds);

        if ($seconds < 60) {
            return $seconds.' dtk';
        }

        if ($seconds < 3600) {
            $m = intdiv($seconds, 60);
            $s = $seconds % 60;

            return $m.' mnt'.($s > 0 ? ' '.$s.' dtk' : '');
        }

        if ($seconds >= 172800) {
            $d = intdiv($seconds, 86400);
            $h = intdiv($seconds % 86400, 3600);

            return $d.' hari'.($h > 0 ? ' '.$h.' jam' : '');
        }

        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);

        return $h.' jam'.($m > 0 ? ' '.$m.' mnt' : '');
    }
}

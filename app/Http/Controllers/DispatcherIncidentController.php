<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DispatcherIncidentController
{
    public const ACTIVE = ['baru', 'diverifikasi', 'ditugaskan', 'ditangani'];

    public const FILTERS = [
        'aktif', 'semua', 'baru', 'diverifikasi', 'ditugaskan', 'ditangani',
        'selesai', 'tidak_terbukti', 'dibatalkan',
    ];

    public function index(Request $request): View
    {
        $filter = $request->query('status', 'aktif');

        if (! is_string($filter) || ! in_array($filter, self::FILTERS, true)) {
            $filter = 'aktif';
        }

        $query = DB::table('incidents')
            ->join('categories', 'categories.id', '=', 'incidents.category_id')
            ->leftJoin('districts', 'districts.id', '=', 'incidents.district_id')
            ->select([
                'incidents.id',
                'incidents.reference',
                'incidents.status',
                'incidents.reported_at',
                'incidents.trust_score',
                'incidents.priority_score',
                'incidents.location_source',
                'incidents.location_accuracy_m',
                'incidents.landmark_text',
                'incidents.reporter_phone',
                'incidents.description',
                'categories.name as category',
                'districts.name as district',
            ])
            ->orderByDesc('incidents.priority_score')
            ->orderBy('incidents.reported_at');

        if ($filter === 'aktif') {
            $query->whereIn('incidents.status', self::ACTIVE);
        } elseif ($filter !== 'semua') {
            $query->where('incidents.status', $filter);
        }

        $counts = DB::table('incidents')
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('dispatcher.index', [
            'incidents' => $query->simplePaginate(25)->withQueryString(),
            'filter' => $filter,
            'filters' => self::FILTERS,
            'activeCount' => collect(self::ACTIVE)->sum(fn ($status) => (int) ($counts[$status] ?? 0)),
            'signature' => $this->signature(),
        ]);
    }

    public function summary(): \Illuminate\Http\JsonResponse
    {
        return response()
            ->json(['signature' => $this->signature()])
            ->header('Cache-Control', 'no-store');
    }

    // Tanda perubahan: jumlah laporan dan nomor log terakhir. Tanpa data pribadi.
    private function signature(): string
    {
        $total = DB::table('incidents')->count();
        $logTerakhir = (int) DB::table('incident_logs')->max('id');

        return $total.'|'.$logTerakhir;
    }
}

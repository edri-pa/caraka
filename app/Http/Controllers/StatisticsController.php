<?php

namespace App\Http\Controllers;

use App\Services\IncidentStatistics;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatisticsController
{
    public function index(Request $request, IncidentStatistics $statistics): View
    {
        $hari = ['7' => 7, '30' => 30];
        $periode = (string) $request->query('periode', 'semua');

        if (! array_key_exists($periode, $hari)) {
            $periode = 'semua';
        }

        $since = $periode === 'semua' ? null : now()->subDays($hari[$periode]);

        return view('statistik', [
            'data' => $statistics->summary($since),
            'periode' => $periode,
            'demo' => \Illuminate\Support\Facades\DB::table('incidents')->where('reference', 'like', 'DEMO%')->count(),
            'format' => fn ($detik) => $statistics->formatDuration($detik),
        ]);
    }
}

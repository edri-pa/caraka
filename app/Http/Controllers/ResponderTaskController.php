<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ResponderTaskController
{
    public const ACTIVE = ['ditugaskan', 'ditangani'];

    public const HISTORY = ['selesai', 'dibatalkan', 'tidak_terbukti'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $tab = $request->query('tab') === 'riwayat' ? 'riwayat' : 'aktif';

        $agency = $user->agency_id !== null
            ? DB::table('agencies')->where('id', $user->agency_id)->first(['id', 'name', 'type'])
            : null;

        $incidents = collect();

        if ($agency !== null) {
            // Sengaja tidak memilih nomor HP atau keterangan: daftar ini hanya ringkasan.
            $incidents = DB::table('incidents')
                ->join('incident_agencies', 'incident_agencies.incident_id', '=', 'incidents.id')
                ->join('categories', 'categories.id', '=', 'incidents.category_id')
                ->leftJoin('districts', 'districts.id', '=', 'incidents.district_id')
                ->where('incident_agencies.agency_id', $agency->id)
                ->whereIn('incidents.status', $tab === 'aktif' ? self::ACTIVE : self::HISTORY)
                ->orderByDesc('incidents.priority_score')
                ->orderBy('incidents.reported_at')
                ->limit(50)
                ->get([
                    'incidents.id',
                    'incidents.reference',
                    'incidents.status',
                    'incidents.reported_at',
                    'incidents.priority_score',
                    'incidents.location_source',
                    'incidents.landmark_text',
                    'categories.name as category',
                    'districts.name as district',
                    'incident_agencies.role as peran',
                ]);
        }

        return view('responder.tugas', [
            'agency' => $agency,
            'incidents' => $incidents,
            'tab' => $tab,
        ]);
    }
}

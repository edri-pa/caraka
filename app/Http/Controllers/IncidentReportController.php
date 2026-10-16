<?php

namespace App\Http\Controllers;

use App\Services\IncidentTrustScorer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class IncidentReportController
{
    // Tanpa huruf I dan O, dan tanpa angka 0 dan 1, supaya tidak tertukar saat dibaca.
    private const REFERENCE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function create(): View
    {
        $categories = DB::table('categories')->orderBy('name')->get();

        return view('lapor.create', [
            'parents' => $categories->whereNull('parent_id')->values(),
            'children' => $categories->whereNotNull('parent_id')->groupBy('parent_id'),
            'districts' => DB::table('districts')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, IncidentTrustScorer $scorer): RedirectResponse
    {
        if ($request->filled('reporter_phone')) {
            $request->merge([
                'reporter_phone' => preg_replace('/[\s\-]+/', '', (string) $request->input('reporter_phone')),
            ]);
        }

        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'reporter_phone' => ['nullable', 'regex:/^(\+62|62|0)[0-9]{8,13}$/'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'district_id' => ['nullable', 'required_without:latitude', 'integer', 'exists:districts,id'],
            'landmark_text' => ['nullable', 'required_without:latitude', 'string', 'max:255'],
        ], [
            'district_id.required_without' => 'Pilih kecamatan, atau ambil lokasi dengan GPS.',
            'landmark_text.required_without' => 'Tulis patokan lokasi, atau ambil lokasi dengan GPS.',
            'reporter_phone.regex' => 'Format nomor HP tidak valid.',
        ]);

        $hasGps = isset($data['latitude'], $data['longitude']);
        $phone = $data['reporter_phone'] ?? null;
        $accuracy = isset($data['accuracy']) ? (float) $data['accuracy'] : null;

        $recent = $phone === null ? 0 : DB::table('incidents')
            ->where('reporter_phone', $phone)
            ->where('reported_at', '>=', now()->subDay())
            ->count();

        $trust = $scorer->score($hasGps, $hasGps ? $accuracy : null, $phone !== null, $recent);
        $priority = (int) DB::table('categories')->where('id', $data['category_id'])->value('base_priority');

        $reference = DB::transaction(function () use ($data, $hasGps, $phone, $accuracy, $trust, $priority) {
            $reference = $this->newReference();

            $location = $hasGps
                ? DB::raw(sprintf(
                    'ST_SetSRID(ST_MakePoint(%F, %F), 4326)::geography',
                    (float) $data['longitude'],
                    (float) $data['latitude']
                ))
                : null;

            $id = DB::table('incidents')->insertGetId([
                'reference' => $reference,
                'reporter_phone' => $phone,
                'category_id' => $data['category_id'],
                'district_id' => $data['district_id'] ?? null,
                'description' => $data['description'] ?? null,
                'landmark_text' => $data['landmark_text'] ?? null,
                'location' => $location,
                'location_accuracy_m' => $hasGps ? $accuracy : null,
                'location_source' => $hasGps ? 'gps' : 'manual',
                'status' => 'baru',
                'trust_score' => $trust,
                'priority_score' => $priority,
                'reported_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('incident_logs')->insert([
                'incident_id' => $id,
                'user_id' => null,
                'action' => 'dilaporkan',
                'from_status' => null,
                'to_status' => 'baru',
                'note' => 'Laporan diterima dari formulir warga ('.($hasGps ? 'GPS' : 'manual').').',
                'created_at' => now(),
            ]);

            return $reference;
        });

        $incidentId = (int) DB::table('incidents')->where('reference', $reference)->value('id');
        app(\App\Services\IncidentBroadcaster::class)->notify($incidentId, 'baru');

        return redirect()->route('lapor.show', $reference)->with('baru', true);
    }

    public function show(string $reference): View
    {
        // Sengaja tidak memilih nomor HP, deskripsi, atau koordinat: halaman ini publik.
        $incident = DB::table('incidents')
            ->join('categories', 'categories.id', '=', 'incidents.category_id')
            ->leftJoin('districts', 'districts.id', '=', 'incidents.district_id')
            ->where('incidents.reference', $reference)
            ->first([
                'incidents.reference',
                'incidents.status',
                'incidents.reported_at',
                'categories.name as category',
                'districts.name as district',
            ]);

        abort_if($incident === null, 404);

        return view('lapor.show', ['incident' => $incident]);
    }

    private function newReference(): string
    {
        $max = strlen(self::REFERENCE_ALPHABET) - 1;

        do {
            $reference = '';

            for ($i = 0; $i < 10; $i++) {
                $reference .= self::REFERENCE_ALPHABET[random_int(0, $max)];
            }
        } while (DB::table('incidents')->where('reference', $reference)->exists());

        return $reference;
    }
}

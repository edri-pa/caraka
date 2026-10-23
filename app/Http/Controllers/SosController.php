<?php

namespace App\Http\Controllers;

use App\Services\IncidentBroadcaster;
use App\Services\IncidentTrustScorer;
use App\Services\ReporterIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SosController
{
    private const REFERENCE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const DUPLICATE_SECONDS = 120;
    private const DETAILS_WINDOW_SECONDS = 1800;

    public function create(): View
    {
        return view('sos.create');
    }

    public function store(Request $request, IncidentTrustScorer $scorer): RedirectResponse
    {
        // Perlindungan klik ganda: SOS terakhir dari sesi ini masih baru.
        $lastReference = $request->session()->get('sos_reference');
        $lastAt = (int) $request->session()->get('sos_at', 0);

        if ($lastReference !== null && (now()->timestamp - $lastAt) < self::DUPLICATE_SECONDS) {
            return redirect()->route('sos.sent', $lastReference);
        }

        // Jalur darurat: koordinat yang tidak valid diabaikan, tidak pernah menolak laporan.
        $lat = $this->number($request->input('latitude'), -90, 90);
        $lng = $this->number($request->input('longitude'), -180, 180);
        $hasGps = $lat !== null && $lng !== null;
        $accuracy = $hasGps ? $this->number($request->input('accuracy'), 0, 1000000) : null;

        $trust = max(10, $scorer->score($hasGps, $accuracy, false, 0) - 20);
        $categoryId = $this->otherCategoryId();

        [$id, $reference] = DB::transaction(function () use ($hasGps, $lat, $lng, $accuracy, $trust, $categoryId) {
            $reference = $this->newReference();

            $id = DB::table('incidents')->insertGetId([
                'reference' => $reference,
                'category_id' => $categoryId,
                'description' => 'LAPORAN SOS (satu ketukan)',
                'location' => $hasGps
                    ? DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)::geography', $lng, $lat))
                    : null,
                'location_accuracy_m' => $accuracy,
                'location_source' => $hasGps ? 'gps' : 'manual',
                'status' => 'baru',
                'trust_score' => $trust,
                'priority_score' => 5,
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
                'note' => 'Laporan SOS satu ketukan ('.($hasGps ? 'dengan GPS' : 'tanpa lokasi').').',
                'created_at' => now(),
            ]);

            return [$id, $reference];
        });

        $request->session()->put('sos_reference', $reference);
        $request->session()->put('sos_at', now()->timestamp);

        app(IncidentBroadcaster::class)->notify($id, 'baru');

        return redirect()->route('sos.sent', $reference);
    }

    public function sent(Request $request, string $reference): View|RedirectResponse
    {
        $incident = DB::table('incidents')->where('reference', $reference)->first(['id', 'location_source']);

        abort_if($incident === null, 404);

        if (! $this->ownsSession($request, $reference)) {
            return redirect()->route('lapor.show', $reference);
        }

        return view('sos.sent', [
            'reference' => $reference,
            'hasLocation' => $incident->location_source === 'gps',
        ]);
    }

    public function addDetails(Request $request, string $reference): RedirectResponse
    {
        abort_unless($this->ownsSession($request, $reference), 403);

        if ($request->filled('reporter_phone')) {
            $request->merge([
                'reporter_phone' => preg_replace('/[\s\-]+/', '', (string) $request->input('reporter_phone')),
            ]);
        }

        $data = $request->validate([
            'has_casualty' => ['nullable', 'in:1,0'],
            'landmark_text' => ['nullable', 'string', 'max:255'],
            'reporter_phone' => ['nullable', 'regex:/^(\+62|62|0)[0-9]{8,13}$/'],
        ], [
            'reporter_phone.regex' => 'Format nomor HP tidak valid.',
        ]);

        $incident = DB::table('incidents')->where('reference', $reference)->first();

        abort_if($incident === null, 404);

        $update = ['updated_at' => now()];
        $catatan = [];

        $korban = match ((string) ($data['has_casualty'] ?? '')) {
            '1' => true,
            '0' => false,
            default => null,
        };

        if ($korban !== null) {
            $update['has_casualty'] = $korban;
            $catatan[] = $korban ? 'ada korban luka' : 'tidak ada korban luka';
        }

        if (! empty($data['landmark_text'])) {
            $update['landmark_text'] = $data['landmark_text'];
            $catatan[] = 'patokan lokasi';
        }

        if (! empty($data['reporter_phone'])) {
            $update['reporter_phone'] = $data['reporter_phone'];
            $update['reporter_hash'] = app(ReporterIdentity::class)->hash($data['reporter_phone']);
            $catatan[] = 'nomor HP';

            if ($incident->reporter_phone === null) {
                $update['trust_score'] = min(90, (int) $incident->trust_score + 10);
            }
        }

        if (count($catatan) > 1 || (count($catatan) === 1)) {
            DB::table('incidents')->where('id', $incident->id)->update($update);

            DB::table('incident_logs')->insert([
                'incident_id' => $incident->id,
                'user_id' => null,
                'action' => 'sos_tambahan',
                'from_status' => $incident->status,
                'to_status' => $incident->status,
                'note' => 'Warga menambahkan: '.implode(', ', $catatan).'.',
                'created_at' => now(),
            ]);

            app(IncidentBroadcaster::class)->notify((int) $incident->id, 'status');
        }

        return redirect()->route('sos.sent', $reference)->with('tersimpan', true);
    }

    private function ownsSession(Request $request, string $reference): bool
    {
        return $request->session()->get('sos_reference') === $reference
            && (now()->timestamp - (int) $request->session()->get('sos_at', 0)) <= self::DETAILS_WINDOW_SECONDS;
    }

    private function number(mixed $value, float $min, float $max): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return ($number >= $min && $number <= $max) ? $number : null;
    }

    private function otherCategoryId(): int
    {
        $id = DB::table('categories')->where('slug', 'lainnya')->value('id');

        if ($id !== null) {
            return (int) $id;
        }

        return (int) DB::table('categories')->insertGetId([
            'name' => 'Lainnya',
            'slug' => 'lainnya',
            'base_priority' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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

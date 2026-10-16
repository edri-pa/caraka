<?php

namespace App\Services;

use App\Events\IncidentUpdated;
use Illuminate\Support\Facades\DB;
use Throwable;

class IncidentBroadcaster
{
    /**
     * Siarkan pembaruan laporan. Kegagalan siaran (misalnya Reverb mati)
     * dicatat di log dan TIDAK PERNAH menggagalkan proses utama.
     */
    public function notify(int $incidentId, string $type): void
    {
        try {
            $agencyIds = DB::table('incident_agencies')
                ->where('incident_id', $incidentId)
                ->pluck('agency_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            IncidentUpdated::dispatch($incidentId, $type, $agencyIds);
        } catch (Throwable $e) {
            report($e);
        }
    }
}

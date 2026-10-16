<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class IncidentUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  int  $incidentId
     * @param  string  $type  baru, status, atau instansi
     * @param  array<int>  $agencyIds  instansi yang terkait dengan laporan
     */
    public function __construct(
        public int $incidentId,
        public string $type,
        public array $agencyIds = [],
    ) {
    }

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('dispatch')];

        foreach (array_unique($this->agencyIds) as $agencyId) {
            $channels[] = new PrivateChannel('agency.'.$agencyId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'incident.updated';
    }

    // Sengaja minimal: tanpa nomor HP, keterangan, atau koordinat.
    public function broadcastWith(): array
    {
        return [
            'incident_id' => $this->incidentId,
            'type' => $this->type,
        ];
    }
}

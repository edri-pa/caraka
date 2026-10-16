<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Channel privat Caraka: aturan akses ada di App\Broadcasting\IncidentChannelAccess.
\Illuminate\Support\Facades\Broadcast::channel('dispatch', function (\App\Models\User $user) {
    return app(\App\Broadcasting\IncidentChannelAccess::class)->dispatch($user);
});

\Illuminate\Support\Facades\Broadcast::channel('agency.{agencyId}', function (\App\Models\User $user, int $agencyId) {
    return app(\App\Broadcasting\IncidentChannelAccess::class)->agency($user, $agencyId);
});

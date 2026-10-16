<?php

namespace App\Broadcasting;

use App\Models\User;

class IncidentChannelAccess
{
    public function dispatch(User $user): bool
    {
        return in_array($user->role, ['dispatcher', 'admin'], true);
    }

    public function agency(User $user, int $agencyId): bool
    {
        return $user->role === 'responder'
            && $user->agency_id !== null
            && (int) $user->agency_id === $agencyId;
    }
}

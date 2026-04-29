<?php

use App\Models\Mission;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('mission.{missionId}', function ($user, int $missionId) {
    $mission = Mission::query()
        ->with(['rendezVous', 'assignments'])
        ->find($missionId);

    if (! $mission) {
        return false;
    }

    if ($user->isAdmin()) {
        return true;
    }

    if ($user->isEmploye()) {
        return (int) $mission->lead_employee_id === (int) $user->id
            || $mission->assignments()->where('user_id', $user->id)->exists();
    }

    if ($user->isClient()) {
        return (int) $mission->rendezVous?->client_id === (int) $user->id
            || (
                $mission->organization_account_id
                && $user->organization_account_id
                && (int) $mission->organization_account_id === (int) $user->organization_account_id
            );
    }

    return false;
});

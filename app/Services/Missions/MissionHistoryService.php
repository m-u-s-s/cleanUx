<?php

namespace App\Services\Missions;

use App\Models\Mission;
use App\Models\MissionEvent;
use App\Models\User;

class MissionHistoryService
{
    public function log(
        Mission $mission,
        ?User $actor,
        string $eventType,
        string $title,
        ?string $description = null,
        array $payload = []
    ): MissionEvent {
        return MissionEvent::query()->create([
            'mission_id' => $mission->id,
            'actor_user_id' => $actor?->id,
            'event_type' => $eventType,
            'title' => $title,
            'description' => $description,
            'payload' => $payload ?: null,
            'happened_at' => now(),
        ]);
    }
}
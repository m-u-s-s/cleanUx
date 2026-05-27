<?php

namespace Database\Factories;

use App\Models\BroadcastEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BroadcastEventFactory extends Factory
{
    protected $model = BroadcastEvent::class;

    public function definition(): array
    {
        $status = fake()->randomElement([
            BroadcastEvent::STATUS_QUEUED,
            BroadcastEvent::STATUS_SENT,
            BroadcastEvent::STATUS_FAILED,
        ]);

        return [
            'channel'        => 'private-user.' . fake()->numberBetween(1, 9999),
            'event_class'    => 'App\\Events\\MissionStatusChanged',
            'broadcast_as'   => 'mission.status.changed',
            'audience'       => BroadcastEvent::AUDIENCE_PER_USER,
            'audience_id'    => fake()->numberBetween(1, 9999),
            'category'       => fake()->randomElement([
                BroadcastEvent::CATEGORY_MISSION_STATUS,
                BroadcastEvent::CATEGORY_NOTIFICATION,
                BroadcastEvent::CATEGORY_CHAT,
            ]),
            'payload'        => ['mission_id' => fake()->numberBetween(1, 500), 'status' => 'completed'],
            'status'         => $status,
            'attempts'       => $status === BroadcastEvent::STATUS_FAILED ? fake()->numberBetween(1, 5) : 1,
            'failed_reason'  => $status === BroadcastEvent::STATUS_FAILED ? 'Connection timeout' : null,
            'source_type'    => 'App\\Models\\Mission',
            'source_id'      => fake()->numberBetween(1, 500),
            'idempotency_key' => Str::uuid()->toString(),
            'queued_at'      => now()->subSeconds(fake()->numberBetween(1, 300)),
            'sent_at'        => $status === BroadcastEvent::STATUS_SENT ? now() : null,
            'failed_at'      => $status === BroadcastEvent::STATUS_FAILED ? now() : null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status'    => BroadcastEvent::STATUS_SENT,
            'sent_at'   => now(),
            'failed_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status'       => BroadcastEvent::STATUS_FAILED,
            'failed_reason' => 'Reverb connection error',
            'failed_at'    => now(),
        ]);
    }
}

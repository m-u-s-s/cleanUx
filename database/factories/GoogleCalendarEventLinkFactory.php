<?php

namespace Database\Factories;

use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarEventLink;
use Illuminate\Database\Eloquent\Factories\Factory;

class GoogleCalendarEventLinkFactory extends Factory
{
    protected $model = GoogleCalendarEventLink::class;

    public function definition(): array
    {
        return [
            'google_calendar_connection_id' => fn () => GoogleCalendarConnection::factory()->create()->id,
            'rendez_vous_id' => null,
            'google_event_id' => fake()->lexify('?????????????????????????'),
            'google_calendar_id' => 'primary',
            'etag' => '"'.fake()->sha1().'"',
            'last_synced_at' => now(),
            'sync_status' => 'synced',
            'last_error' => null,
            'meta' => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'sync_status' => 'failed',
            'last_error' => 'Event not found',
        ]);
    }
}

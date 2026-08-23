<?php

namespace Tests\Feature\Client\Calendar;

use App\Livewire\Client\Calendar\ClientCalendarFC;
use App\Models\Booking;
use App\Models\User;
use App\Services\Client\Calendar\CalendarDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** P0.3 — CalendarDataService (previously missing → ClientCalendarFC fatal on render). */
class CalendarDataServiceP0Test extends TestCase
{
    use RefreshDatabase;

    public function test_events_for_user_are_scoped_and_mapped(): void
    {
        $client = User::factory()->client()->create();
        $other = User::factory()->client()->create();

        $mine = Booking::factory()->create([
            'client_id' => $client->id,
            'date' => now()->addDays(2),
            'heure' => '10:00',
            'status' => 'confirme',
        ]);
        Booking::factory()->create([
            'client_id' => $other->id,
            'date' => now()->addDays(2),
            'status' => 'confirme',
        ]);

        $events = app(CalendarDataService::class)->eventsForUser($client, [
            'from' => now()->subDay(),
            'to' => now()->addDays(10),
        ]);

        $this->assertCount(1, $events, 'only the user\'s own booking is returned');
        $this->assertSame($mine->id, $events->first()['id']);
        $this->assertNotNull($events->first()['start']);
        $this->assertSame('confirme', $events->first()['status']);
    }

    public function test_status_filter_applies(): void
    {
        $client = User::factory()->client()->create();
        Booking::factory()->create(['client_id' => $client->id, 'date' => now()->addDay(), 'status' => 'confirme']);
        Booking::factory()->create(['client_id' => $client->id, 'date' => now()->addDay(), 'status' => 'annule']);

        $events = app(CalendarDataService::class)->eventsForUser($client, [
            'from' => now()->subDay(),
            'to' => now()->addDays(5),
            'statuses' => ['confirme'],
        ]);

        $this->assertCount(1, $events);
    }

    public function test_component_renders_without_the_previously_missing_service(): void
    {
        $client = User::factory()->client()->create();

        // Before P0.3 this fatally errored (CalendarDataService did not exist).
        Livewire::actingAs($client)
            ->test(ClientCalendarFC::class)
            ->assertOk();
    }
}

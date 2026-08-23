<?php

namespace Tests\Feature\Livewire\Client;

use App\Livewire\Client\Calendar\ClientCalendarFC;
use App\Models\Booking;
use App\Models\User;
use App\Services\Client\Calendar\CalendarDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ClientCalendarFCCoverageBatch9Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The real CalendarDataService class does not exist in the codebase
        // (it is referenced but never implemented). Bind a lightweight test
        // double into the container so the component can render and fetch.
        $this->fakeCalendarService();
    }

    /** Binds a test double for the (non-existent) CalendarDataService so that render() and fetchEvents() are exercisable. */
    private function fakeCalendarService(array $events = [], array $sites = []): object
    {
        $fake = new class
        {
            public array $events = [];

            public array $sites = [];

            public function eventsForUser($user, array $options = [])
            {
                return collect($this->events);
            }

            public function availableSitesForUser($user)
            {
                return collect($this->sites);
            }
        };

        $fake->events = $events;
        $fake->sites = $sites;

        $this->app->instance(CalendarDataService::class, $fake);

        return $fake;
    }

    public function test_render_lists_available_sites(): void
    {
        $user = User::factory()->client()->create();
        $this->fakeCalendarService(sites: [
            ['id' => 1, 'name' => 'Site Alpha'],
            ['id' => 2, 'name' => 'Site Beta'],
        ]);

        Livewire::actingAs($user)
            ->test(ClientCalendarFC::class)
            ->assertOk()
            ->assertSee('Calendrier interactif')
            ->assertSee('Site Alpha')
            ->assertSee('Site Beta');
    }

    public function test_fetch_events_maps_payload_and_computes_editability(): void
    {
        $user = User::factory()->client()->create();
        $this->fakeCalendarService(events: [
            [
                'id' => 11,
                'title' => 'Nettoyage bureaux',
                'start' => '2026-06-10T08:00:00',
                'end' => '2026-06-10T10:00:00',
                'color' => '#2563eb',
                'status' => 'confirme',
                'site_name' => 'Site Alpha',
                'service_name' => 'Nettoyage',
                'reference' => 'CUX-1',
            ],
            [
                'id' => 22,
                'title' => 'Mission terminée',
                'start' => '2026-06-11T08:00:00',
                'end' => '2026-06-11T10:00:00',
                'color' => '#9ca3af',
                'status' => 'termine',
            ],
        ]);

        Livewire::actingAs($user)
            ->test(ClientCalendarFC::class)
            ->call('fetchEvents', '2026-06-01', '2026-06-30')
            ->assertReturned(function ($returned) {
                if (! is_array($returned) || count($returned) !== 2) {
                    return false;
                }

                $first = $returned[0];
                $second = $returned[1];

                return $first['id'] === 11
                    && $first['title'] === 'Nettoyage bureaux'
                    && $first['backgroundColor'] === '#2563eb'
                    && $first['borderColor'] === '#2563eb'
                    && $first['editable'] === true
                    && $first['extendedProps']['site_name'] === 'Site Alpha'
                    && $first['extendedProps']['service_name'] === 'Nettoyage'
                    && $first['extendedProps']['reference'] === 'CUX-1'
                    && $second['editable'] === false
                    && $second['extendedProps']['site_name'] === null;
            });
    }

    public function test_fetch_events_passes_active_filters(): void
    {
        $user = User::factory()->client()->create();
        $this->fakeCalendarService(events: []);

        Livewire::actingAs($user)
            ->test(ClientCalendarFC::class)
            ->set('siteIds', [7])
            ->set('statuses', ['confirme'])
            ->call('fetchEvents', '2026-06-01', '2026-06-30')
            ->assertReturned(fn ($returned) => $returned === []);
    }

    public function test_handle_event_drop_reverts_when_booking_missing(): void
    {
        $user = User::factory()->client()->create();

        Livewire::actingAs($user)
            ->test(ClientCalendarFC::class)
            ->call('handleEventDrop', 999999, Carbon::now()->addDays(5)->toIso8601String())
            ->assertSet('message', 'Réservation introuvable.')
            ->assertSet('messageType', 'error')
            ->assertDispatched('calendar:revert');
    }

    public function test_handle_event_drop_reschedules_booking_successfully(): void
    {
        $user = User::factory()->client()->create();
        $booking = Booking::factory()->create([
            'client_id' => $user->id,
            'status' => 'confirme',
        ]);

        $newStart = Carbon::now()->addDays(10)->setTime(9, 0, 0);

        Livewire::actingAs($user)
            ->test(ClientCalendarFC::class)
            ->call('handleEventDrop', $booking->id, $newStart->toIso8601String())
            ->assertSet('messageType', 'success')
            ->assertDispatched('calendar:refresh');

        $this->assertSame(
            $newStart->toDateString(),
            Carbon::parse($booking->fresh()->scheduled_date)->toDateString()
        );

        $this->assertDatabaseHas('booking_reschedule_history', [
            'booking_id' => $booking->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_handle_event_drop_flashes_domain_exception_message(): void
    {
        $user = User::factory()->client()->create();
        $booking = Booking::factory()->termine()->create([
            'client_id' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(ClientCalendarFC::class)
            ->call('handleEventDrop', $booking->id, Carbon::now()->addDays(5)->toIso8601String())
            ->assertSet('messageType', 'error')
            ->assertSet('message', 'Cette réservation ne peut plus être reprogrammée (statut: termine).')
            ->assertDispatched('calendar:revert');
    }

    public function test_select_and_clear_selection_toggle_state(): void
    {
        $user = User::factory()->client()->create();
        $booking = Booking::factory()->create(['client_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(ClientCalendarFC::class)
            ->call('selectEvent', $booking->id)
            ->assertSet('selectedBookingId', $booking->id)
            ->assertSee($booking->booking_reference)
            ->call('clearSelection')
            ->assertSet('selectedBookingId', null);
    }

    public function test_clear_message_resets_flash_state(): void
    {
        $user = User::factory()->client()->create();

        Livewire::actingAs($user)
            ->test(ClientCalendarFC::class)
            ->call('handleEventDrop', 999999, Carbon::now()->addDays(5)->toIso8601String())
            ->assertSet('message', 'Réservation introuvable.')
            ->call('clearMessage')
            ->assertSet('message', null)
            ->assertSet('messageType', null);
    }
}

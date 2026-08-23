<?php

namespace Tests\Feature\Calendar;

use App\Models\AvailabilityException;
use App\Models\Booking;
use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarWatchChannel;
use App\Models\User;
use App\Services\Calendar\Contracts\GoogleBusyFetcher;
use App\Services\Calendar\Fetchers\MockGoogleBusyFetcher;
use App\Services\Calendar\GoogleCalendarBidirectionalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** GCal bidirectionnel — sens entrant : un créneau bloqué dans Google Calendar devient une exception d'indisponibilité côté Brio, et entre en conflit avec une réservation chevauchante. */
class GoogleCalendarInboundTest extends TestCase
{
    use RefreshDatabase;

    private function service(): GoogleCalendarBidirectionalService
    {
        return app(GoogleCalendarBidirectionalService::class);
    }

    public function test_timed_busy_event_becomes_a_partial_exception(): void
    {
        $provider = User::factory()->employe()->create();

        $this->service()->importBusyEvents($provider, [
            ['external_id' => 'evt-1', 'start' => '2026-06-20T14:00:00', 'end' => '2026-06-20T16:00:00', 'all_day' => false, 'summary' => 'Dentiste'],
        ]);

        $ex = AvailabilityException::where('provider_user_id', $provider->id)->first();
        $this->assertNotNull($ex);
        $this->assertSame(AvailabilityException::TYPE_PARTIAL, $ex->exception_type);
        $this->assertSame('14:00:00', substr((string) $ex->start_time, 0, 8));
        $this->assertSame('16:00:00', substr((string) $ex->end_time, 0, 8));
        $this->assertSame('google', $ex->metadata['source'] ?? null);
        $this->assertSame('evt-1', $ex->metadata['external_id'] ?? null);
    }

    public function test_all_day_event_becomes_a_closed_exception(): void
    {
        $provider = User::factory()->employe()->create();

        $this->service()->importBusyEvents($provider, [
            ['external_id' => 'evt-ad', 'start' => '2026-06-21', 'end' => '2026-06-22', 'all_day' => true, 'summary' => 'Congé'],
        ]);

        $ex = AvailabilityException::where('provider_user_id', $provider->id)->first();
        $this->assertSame(AvailabilityException::TYPE_CLOSED, $ex->exception_type);
    }

    public function test_import_is_full_replace_for_google_sourced_only(): void
    {
        $provider = User::factory()->employe()->create();

        // Une exception manuelle (non-google) ne doit pas être touchée.
        $manual = AvailabilityException::create([
            'provider_user_id' => $provider->id,
            'date' => '2026-06-20',
            'exception_type' => AvailabilityException::TYPE_CLOSED,
            'reason' => 'manuel',
        ]);

        $this->service()->importBusyEvents($provider, [
            ['external_id' => 'evt-1', 'start' => '2026-06-20T14:00:00', 'end' => '2026-06-20T16:00:00', 'all_day' => false],
        ]);
        // Ré-import avec un autre événement → l'ancien google disparaît, le manuel reste.
        $this->service()->importBusyEvents($provider, [
            ['external_id' => 'evt-2', 'start' => '2026-06-22T09:00:00', 'end' => '2026-06-22T10:00:00', 'all_day' => false],
        ]);

        $this->assertDatabaseHas('availability_exceptions', ['id' => $manual->id]);
        $google = AvailabilityException::where('provider_user_id', $provider->id)
            ->where('metadata->source', 'google')->get();
        $this->assertCount(1, $google);
        $this->assertSame('evt-2', $google->first()->metadata['external_id']);
    }

    public function test_has_conflict_detects_overlap(): void
    {
        $provider = User::factory()->employe()->create();
        $this->service()->importBusyEvents($provider, [
            ['external_id' => 'evt-1', 'start' => '2026-06-20T14:00:00', 'end' => '2026-06-20T16:00:00', 'all_day' => false],
        ]);

        $overlap = Booking::factory()->create([
            'employe_id' => $provider->id, 'date' => '2026-06-20', 'heure' => '15:00:00', 'duree_estimee' => 60,
        ]);
        $clear = Booking::factory()->create([
            'employe_id' => $provider->id, 'date' => '2026-06-20', 'heure' => '17:00:00', 'duree_estimee' => 60,
        ]);

        $this->assertTrue($this->service()->hasConflict($provider, $overlap));
        $this->assertFalse($this->service()->hasConflict($provider, $clear));
    }

    public function test_push_notification_imports_busy_events_for_channel_provider(): void
    {
        $mock = new MockGoogleBusyFetcher;
        $mock->events = [
            ['external_id' => 'evt-z', 'start' => '2026-06-25T10:00:00', 'end' => '2026-06-25T11:00:00', 'all_day' => false],
        ];
        $this->app->instance(GoogleBusyFetcher::class, $mock);

        $provider = User::factory()->employe()->create();
        $conn = GoogleCalendarConnection::create([
            'user_id' => $provider->id, 'calendar_id' => 'primary', 'sync_enabled' => true, 'access_token' => 'tok',
        ]);
        GoogleCalendarWatchChannel::create([
            'user_id' => $provider->id, 'connection_id' => $conn->id,
            'channel_id' => 'ch1', 'resource_id' => 'res1', 'calendar_id' => 'primary', 'expires_at' => now()->addDays(7),
        ]);

        app(GoogleCalendarBidirectionalService::class)->handlePushNotification('ch1', 'res1');

        $this->assertSame(1, AvailabilityException::where('provider_user_id', $provider->id)
            ->where('metadata->source', 'google')->count());
    }

    public function test_register_watch_persists_the_channel(): void
    {
        $mock = new MockGoogleBusyFetcher;
        $mock->channelId = 'ch-new';
        $mock->resourceId = 'res-new';
        $this->app->instance(GoogleBusyFetcher::class, $mock);

        $provider = User::factory()->employe()->create();
        GoogleCalendarConnection::create([
            'user_id' => $provider->id, 'calendar_id' => 'primary', 'sync_enabled' => true, 'access_token' => 'tok',
        ]);

        $res = app(GoogleCalendarBidirectionalService::class)->registerWatch($provider);

        $this->assertSame('ch-new', $res['channel_id']);
        $this->assertDatabaseHas('google_calendar_watch_channels', [
            'user_id' => $provider->id, 'channel_id' => 'ch-new', 'resource_id' => 'res-new',
        ]);
    }

    public function test_webhook_endpoint_triggers_import(): void
    {
        $mock = new MockGoogleBusyFetcher;
        $mock->events = [
            ['external_id' => 'evt-w', 'start' => '2026-06-26T09:00:00', 'end' => '2026-06-26T10:00:00', 'all_day' => false],
        ];
        $this->app->instance(GoogleBusyFetcher::class, $mock);

        $provider = User::factory()->employe()->create();
        $conn = GoogleCalendarConnection::create([
            'user_id' => $provider->id, 'calendar_id' => 'primary', 'sync_enabled' => true, 'access_token' => 'tok',
        ]);
        GoogleCalendarWatchChannel::create([
            'user_id' => $provider->id, 'connection_id' => $conn->id,
            'channel_id' => 'chw', 'resource_id' => 'resw', 'calendar_id' => 'primary', 'expires_at' => now()->addDays(7),
        ]);

        $this->post('/webhooks/google-calendar', [], [
            'X-Goog-Channel-ID' => 'chw',
            'X-Goog-Resource-ID' => 'resw',
        ])->assertOk();

        $this->assertSame(1, AvailabilityException::where('provider_user_id', $provider->id)
            ->where('metadata->source', 'google')->count());
    }
}

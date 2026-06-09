<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarEventLink;
use App\Models\User;
use App\Services\Integrations\GoogleCalendarOAuthService;
use App\Services\Integrations\GoogleCalendarSyncService;
use App\Support\Domain\BookingStatus;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleCalendarSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a sync service whose Guzzle client replays the queued responses
     * and whose OAuth dependency always yields a fixed access token.
     *
     * @param  array<int, Response>  $responses
     */
    private function makeService(array $responses = []): GoogleCalendarSyncService
    {
        $oauth = $this->createMock(GoogleCalendarOAuthService::class);
        $oauth->method('accessTokenFor')->willReturn('fake-access-token');

        $http = new Client([
            'handler' => HandlerStack::create(new MockHandler($responses)),
        ]);

        return new GoogleCalendarSyncService($oauth, $http);
    }

    private function makeEmployeeWithConnection(): array
    {
        $employee = User::factory()->employe()->create();
        $connection = GoogleCalendarConnection::factory()->create([
            'user_id' => $employee->id,
            'sync_enabled' => true,
        ]);

        return [$employee, $connection];
    }

    private function futureBookingFor(User $employee, string $status = BookingStatus::CONFIRME): Booking
    {
        return Booking::factory()->create([
            'employe_id' => $employee->id,
            'status' => $status,
            'date' => now()->addDays(3)->toDateString(),
            'heure' => '09:30:00',
        ]);
    }

    public function test_sync_rendez_vous_creates_remote_event_and_persists_link(): void
    {
        [$employee, $connection] = $this->makeEmployeeWithConnection();
        $booking = $this->futureBookingFor($employee);

        $service = $this->makeService([
            new Response(200, [], json_encode([
                'id' => 'evt-123',
                'etag' => '"etag-1"',
                'htmlLink' => 'https://calendar.google.com/evt-123',
            ])),
        ]);

        $result = $service->syncRendezVous($booking, $connection);

        $this->assertSame('created', $result);
        $this->assertDatabaseHas('google_calendar_event_links', [
            'google_calendar_connection_id' => $connection->id,
            'rendez_vous_id' => $booking->id,
            'google_event_id' => 'evt-123',
            'sync_status' => 'created',
        ]);
    }

    public function test_sync_rendez_vous_updates_existing_link(): void
    {
        [$employee, $connection] = $this->makeEmployeeWithConnection();
        $booking = $this->futureBookingFor($employee);

        $link = GoogleCalendarEventLink::factory()->create([
            'google_calendar_connection_id' => $connection->id,
            'rendez_vous_id' => $booking->id,
            'google_event_id' => 'evt-existing',
            'sync_status' => 'created',
        ]);

        $service = $this->makeService([
            new Response(200, [], json_encode([
                'id' => 'evt-existing',
                'etag' => '"etag-2"',
                'htmlLink' => 'https://calendar.google.com/evt-existing',
            ])),
        ]);

        $result = $service->syncRendezVous($booking, $connection);

        $this->assertSame('updated', $result);
        $this->assertDatabaseHas('google_calendar_event_links', [
            'id' => $link->id,
            'sync_status' => 'updated',
            'etag' => '"etag-2"',
        ]);
    }

    public function test_sync_rendez_vous_deletes_link_when_booking_cancelled(): void
    {
        [$employee, $connection] = $this->makeEmployeeWithConnection();
        $booking = $this->futureBookingFor($employee, BookingStatus::ANNULE);

        $link = GoogleCalendarEventLink::factory()->create([
            'google_calendar_connection_id' => $connection->id,
            'rendez_vous_id' => $booking->id,
            'google_event_id' => 'evt-del',
        ]);

        $service = $this->makeService([
            new Response(204, []),
        ]);

        $result = $service->syncRendezVous($booking, $connection);

        $this->assertSame('deleted', $result);
        $this->assertDatabaseMissing('google_calendar_event_links', [
            'id' => $link->id,
        ]);
    }

    public function test_sync_rendez_vous_skips_cancelled_booking_without_link(): void
    {
        [$employee, $connection] = $this->makeEmployeeWithConnection();
        $booking = $this->futureBookingFor($employee, BookingStatus::REFUSE);

        // No HTTP responses queued: a network call would throw, proving none happens.
        $service = $this->makeService();

        $this->assertSame('skipped', $service->syncRendezVous($booking, $connection));
    }

    public function test_sync_rendez_vous_skips_when_no_connection(): void
    {
        $employee = User::factory()->employe()->create();
        $booking = $this->futureBookingFor($employee);

        $service = $this->makeService();

        // No connection passed and none exists in DB for this employee.
        $this->assertSame('skipped', $service->syncRendezVous($booking));
    }

    public function test_sync_future_rendez_vous_returns_error_without_active_connection(): void
    {
        $employee = User::factory()->employe()->create();

        $service = $this->makeService();
        $stats = $service->syncFutureRendezVousForUser($employee);

        $this->assertSame(0, $stats['created']);
        $this->assertNotEmpty($stats['errors']);
        $this->assertStringContainsString('Aucune connexion Google active', $stats['errors'][0]);
    }

    public function test_sync_future_rendez_vous_syncs_window_and_marks_connection(): void
    {
        [$employee, $connection] = $this->makeEmployeeWithConnection();
        $this->futureBookingFor($employee);

        $service = $this->makeService([
            new Response(200, [], json_encode([
                'id' => 'evt-window',
                'etag' => '"etag-w"',
                'htmlLink' => 'https://calendar.google.com/evt-window',
            ])),
        ]);

        $stats = $service->syncFutureRendezVousForUser($employee, 30);

        $this->assertSame(1, $stats['created']);
        $this->assertSame(0, $stats['updated']);
        $this->assertEmpty($stats['errors']);

        $connection->refresh();
        $this->assertNotNull($connection->last_synced_at);
        $this->assertSame('ok', $connection->last_sync_status);
        $this->assertNull($connection->last_sync_error);
    }

    public function test_health_summary_aggregates_connection_state(): void
    {
        $fresh = User::factory()->employe()->create();
        GoogleCalendarConnection::factory()->create([
            'user_id' => $fresh->id,
            'sync_enabled' => true,
            'last_synced_at' => now(),
            'last_sync_status' => 'ok',
        ]);

        $stale = User::factory()->employe()->create();
        $staleConnection = GoogleCalendarConnection::factory()->expired()->create([
            'user_id' => $stale->id,
            'sync_enabled' => true,
            'last_synced_at' => now()->subDays(3),
            'last_sync_status' => 'partial_error',
            'last_sync_error' => 'boom',
            'refresh_token' => null,
        ]);

        GoogleCalendarEventLink::factory()->failed()->create([
            'google_calendar_connection_id' => $staleConnection->id,
            'sync_status' => 'error',
        ]);

        $service = $this->makeService();
        $summary = $service->healthSummary(24);

        $this->assertSame(2, $summary['active_connections']);
        $this->assertSame(1, $summary['stale_connections']);
        $this->assertSame(1, $summary['error_connections']);
        $this->assertSame(1, $summary['expired_tokens']);
        $this->assertSame(1, $summary['failed_event_links']);
    }
}

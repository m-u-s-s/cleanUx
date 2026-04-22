<?php

namespace App\Services\Integrations;

use App\Models\ActivityLog;
use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarEventLink;
use App\Models\RendezVous;
use App\Models\User;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class GoogleCalendarSyncService
{
    public function __construct(
        private readonly GoogleCalendarOAuthService $oauth,
        private readonly Client $http = new Client(['timeout' => 20])
    ) {
    }

    public function syncFutureRendezVousForUser(User $user, int $futureDays = 30): array
    {
        $connection = GoogleCalendarConnection::query()
            ->where('user_id', $user->id)
            ->where('sync_enabled', true)
            ->first();

        if (! $connection) {
            return ['created' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => 0, 'errors' => ['Aucune connexion Google active.']];
        }

        $stats = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => 0, 'errors' => []];

        $rendezVousItems = RendezVous::query()
            ->with(['client', 'serviceCatalog', 'serviceZone', 'organizationSite', 'postalCode'])
            ->where('employe_id', $user->id)
            ->whereDate('date', '>=', now()->toDateString())
            ->whereDate('date', '<=', now()->addDays($futureDays)->toDateString())
            ->whereNotIn('status', ['refuse'])
            ->orderBy('date')
            ->orderBy('heure')
            ->get();

        foreach ($rendezVousItems as $rendezVous) {
            try {
                $result = $this->syncRendezVous($rendezVous, $connection);
                $stats[$result]++;
            } catch (\Throwable $e) {
                $stats['errors'][] = sprintf('RDV #%d : %s', $rendezVous->id, $e->getMessage());
            }
        }

        $connection->forceFill([
            'last_synced_at' => now(),
            'last_sync_status' => empty($stats['errors']) ? 'ok' : 'partial_error',
            'last_sync_error' => empty($stats['errors']) ? null : implode("\n", array_slice($stats['errors'], 0, 3)),
        ])->save();

        return $stats;
    }

    public function syncRendezVous(RendezVous $rendezVous, ?GoogleCalendarConnection $connection = null): string
    {
        $connection ??= GoogleCalendarConnection::query()
            ->where('user_id', $rendezVous->employe_id)
            ->where('sync_enabled', true)
            ->first();

        if (! $connection) {
            return 'skipped';
        }

        $existingLink = GoogleCalendarEventLink::query()
            ->where('google_calendar_connection_id', $connection->id)
            ->where('rendez_vous_id', $rendezVous->id)
            ->first();

        if ($rendezVous->status === 'refuse') {
            if ($existingLink) {
                $this->deleteRemoteEvent($connection, $existingLink);
                $existingLink->delete();
                return 'deleted';
            }

            return 'skipped';
        }

        [$start, $end] = $this->resolveTimes($rendezVous, $connection->user);
        $payload = $this->buildGoogleEventPayload($rendezVous, $start, $end);

        return DB::transaction(function () use ($connection, $existingLink, $payload, $rendezVous) {
            if ($existingLink) {
                $result = $this->updateRemoteEvent($connection, $existingLink, $payload);
                $existingLink->forceFill([
                    'etag' => Arr::get($result, 'etag'),
                    'last_synced_at' => now(),
                    'sync_status' => 'updated',
                    'last_error' => null,
                    'meta' => [
                        'html_link' => Arr::get($result, 'htmlLink'),
                    ],
                ])->save();

                $this->log('google_calendar.event.updated', $rendezVous->id, [
                    'event_id' => $existingLink->google_event_id,
                ]);

                return 'updated';
            }

            $result = $this->createRemoteEvent($connection, $payload);

            GoogleCalendarEventLink::query()->create([
                'google_calendar_connection_id' => $connection->id,
                'rendez_vous_id' => $rendezVous->id,
                'google_event_id' => (string) Arr::get($result, 'id'),
                'google_calendar_id' => $connection->calendar_id ?: 'primary',
                'etag' => Arr::get($result, 'etag'),
                'last_synced_at' => now(),
                'sync_status' => 'created',
                'last_error' => null,
                'meta' => [
                    'html_link' => Arr::get($result, 'htmlLink'),
                ],
            ]);

            $this->log('google_calendar.event.created', $rendezVous->id, [
                'google_event_id' => Arr::get($result, 'id'),
            ]);

            return 'created';
        });
    }

    public function healthSummary(int $staleHours = 24): array
    {
        $connections = GoogleCalendarConnection::query()->where('sync_enabled', true)->get();

        return [
            'active_connections' => $connections->count(),
            'stale_connections' => $connections->filter(fn ($connection) => ! $connection->last_synced_at || $connection->last_synced_at->lt(now()->subHours($staleHours)))->count(),
            'error_connections' => $connections->filter(fn ($connection) => in_array($connection->last_sync_status, ['error', 'partial_error'], true) || filled($connection->last_sync_error))->count(),
            'expired_tokens' => $connections->filter(fn ($connection) => $connection->tokenExpired() || blank($connection->refresh_token))->count(),
            'failed_event_links' => GoogleCalendarEventLink::query()->whereIn('sync_status', ['error', 'partial_error'])->count(),
        ];
    }

    private function resolveTimes(RendezVous $rendezVous, User $user): array
    {
        $timezone = $user->timezone ?: 'Europe/Brussels';
        $date = $rendezVous->date instanceof Carbon ? $rendezVous->date->format('Y-m-d') : (string) $rendezVous->date;
        $heure = substr((string) $rendezVous->heure, 0, 8) ?: '09:00:00';

        $start = Carbon::parse($date . ' ' . $heure, $timezone);

        $duration = (int) ($rendezVous->duree_estimee
            ?: $rendezVous->duree
            ?: $rendezVous->serviceCatalog?->default_duration_minutes
            ?: 120);

        $end = (clone $start)->addMinutes(max(15, $duration));

        return [$start, $end];
    }

    private function buildGoogleEventPayload(RendezVous $rendezVous, Carbon $start, Carbon $end): array
    {
        $serviceName = $rendezVous->service_display_name ?: 'Mission CleanUx';
        $zoneName = $rendezVous->serviceZone?->name ?: 'Zone non définie';
        $siteName = $rendezVous->organizationSite?->name;
        $location = $rendezVous->location_display;

        $descriptionLines = array_filter([
            'Référence : ' . ($rendezVous->booking_reference ?: 'RDV-' . $rendezVous->id),
            'Client : ' . ($rendezVous->client?->name ?: 'N/A'),
            'Téléphone : ' . ($rendezVous->telephone_client ?: $rendezVous->client?->phone ?: 'N/A'),
            'Zone : ' . $zoneName,
            $siteName ? 'Site : ' . $siteName : null,
            'Adresse : ' . $location,
            'Service : ' . $serviceName,
            'Identifiant service : ' . $rendezVous->service_identifier_display,
            'Motif : ' . ($rendezVous->motif ?: '—'),
            'Commentaire : ' . ($rendezVous->commentaire_client ?: '—'),
            'Statut : ' . $rendezVous->status,
        ]);

        return [
            'summary' => 'CleanUx · ' . $serviceName . ' · ' . $zoneName,
            'description' => implode("\n", $descriptionLines),
            'location' => $location,
            'start' => [
                'dateTime' => $start->toIso8601String(),
                'timeZone' => $start->timezoneName,
            ],
            'end' => [
                'dateTime' => $end->toIso8601String(),
                'timeZone' => $end->timezoneName,
            ],
            'extendedProperties' => [
                'private' => [
                    'cleanux_rendez_vous_id' => (string) $rendezVous->id,
                    'cleanux_booking_reference' => (string) ($rendezVous->booking_reference ?: ''),
                    'cleanux_service_zone_id' => (string) ($rendezVous->service_zone_id ?: ''),
                    'cleanux_service_catalog_id' => (string) ($rendezVous->service_catalog_id ?: ''),
                    'cleanux_service_identifier' => (string) $rendezVous->service_identifier_display,
                ],
            ],
            'source' => [
                'title' => 'CleanUx',
                'url' => rtrim(config('app.url'), '/') . '/dashboard',
            ],
        ];
    }

    private function createRemoteEvent(GoogleCalendarConnection $connection, array $payload): array
    {
        $response = $this->http->post($this->eventEndpoint($connection), [
            'headers' => $this->headers($connection),
            'json' => $payload,
        ]);

        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function updateRemoteEvent(GoogleCalendarConnection $connection, GoogleCalendarEventLink $link, array $payload): array
    {
        $response = $this->http->put($this->eventEndpoint($connection, $link->google_event_id), [
            'headers' => $this->headers($connection),
            'json' => $payload,
        ]);

        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function deleteRemoteEvent(GoogleCalendarConnection $connection, GoogleCalendarEventLink $link): void
    {
        $this->http->delete($this->eventEndpoint($connection, $link->google_event_id), [
            'headers' => $this->headers($connection),
        ]);
    }

    private function headers(GoogleCalendarConnection $connection): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->oauth->accessTokenFor($connection),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    private function eventEndpoint(GoogleCalendarConnection $connection, ?string $eventId = null): string
    {
        $calendarId = rawurlencode($connection->calendar_id ?: 'primary');
        $base = sprintf('https://www.googleapis.com/calendar/v3/calendars/%s/events', $calendarId);

        return $eventId ? $base . '/' . rawurlencode($eventId) : $base;
    }

    private function log(string $action, ?int $targetId, array $meta = []): void
    {
        if (! class_exists(ActivityLog::class)) {
            return;
        }

        ActivityLog::query()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'target_type' => 'rendez_vous',
            'target_id' => $targetId,
            'meta' => $meta,
        ]);
    }
}

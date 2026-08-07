<?php

namespace App\Services\Calendar;

use App\Models\AvailabilityException;
use App\Models\Booking;
use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarWatchChannel;
use App\Models\User;
use App\Services\Calendar\Contracts\GoogleBusyFetcher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Synchro Google Calendar BIDIRECTIONNELLE.
 *
 * Sens sortant (existant) : GoogleCalendarSyncService pousse les RDV Brio →
 * Google Calendar (cron google-calendar:sync).
 *
 * Sens entrant (ce service) : un créneau bloqué dans Google Calendar devient une
 * exception d'indisponibilité côté Brio.
 *   - registerWatch()          : ouvre un canal push Google (table google_calendar_watch_channels).
 *   - webhook POST /webhooks/google-calendar → handlePushNotification()
 *   - handlePushNotification() : récupère les créneaux occupés (GoogleBusyFetcher)
 *                                → importBusyEvents() les mappe en AvailabilityException
 *                                (PARTIAL pour un créneau horaire, CLOSED pour une journée).
 *   - hasConflict()            : détecte un chevauchement entre une réservation et un blocage Google.
 *
 * Provider-agnostic via GoogleBusyFetcher (Mock par défaut). Pièces externes
 * restantes : lier le fetcher Google API réel (appels Calendar API) + cron de
 * renouvellement des canaux (expirent après ~7 j) + brancher hasConflict() en
 * soft-warn au moment de l'assignation dispatch si souhaité.
 */
class GoogleCalendarBidirectionalService
{
    public function __construct(protected GoogleBusyFetcher $fetcher) {}

    /**
     * Enregistre un canal de notification push sur le calendrier du prestataire et
     * persiste ses identifiants (pour router les notifications entrantes).
     *
     * @return array{channel_id: string, resource_id: string, expiry: string}
     */
    public function registerWatch(User $provider): array
    {
        $connection = GoogleCalendarConnection::query()->where('user_id', $provider->id)->first();
        if (! $connection) {
            throw new \RuntimeException('Aucune connexion Google Calendar pour ce prestataire.');
        }

        $callback = url('/webhooks/google-calendar');
        $channel = $this->fetcher->registerWatch($connection, $callback);

        GoogleCalendarWatchChannel::updateOrCreate(
            ['channel_id' => $channel['channel_id']],
            [
                'user_id' => $provider->id,
                'connection_id' => $connection->id,
                'resource_id' => $channel['resource_id'],
                'calendar_id' => $connection->calendar_id ?: 'primary',
                'expires_at' => $channel['expiry'] ?? null,
            ]
        );

        return $channel;
    }

    /**
     * Traite une notification push Google : retrouve le prestataire via le canal,
     * récupère ses créneaux occupés et les importe en exceptions d'indisponibilité.
     */
    public function handlePushNotification(string $channelId, string $resourceId): void
    {
        $channel = GoogleCalendarWatchChannel::query()
            ->where('channel_id', $channelId)
            ->where('resource_id', $resourceId)
            ->first();

        if (! $channel) {
            return;
        }

        $connection = $channel->connection
            ?? GoogleCalendarConnection::query()->where('user_id', $channel->user_id)->first();
        $provider = $channel->user;

        if (! $connection || ! $provider) {
            return;
        }

        $events = $this->fetcher->fetchBusyEvents(
            $connection,
            CarbonImmutable::now(),
            CarbonImmutable::now()->addDays(30),
        );

        $this->importBusyEvents($provider, $events);
    }

    /**
     * Sens entrant — mappe les événements « busy » Google d'un prestataire vers des
     * exceptions d'indisponibilité Brio. Remplacement complet des exceptions
     * issues de Google (source=google) ; les exceptions manuelles sont préservées.
     *
     * Événement attendu : ['external_id', 'start', 'end', 'all_day'(bool), 'summary'?].
     * Événement horaire même jour → PARTIAL (soustrait la plage). Journée entière ou
     * multi-jours → CLOSED pour chaque jour concerné.
     *
     * @param  array<int, array<string, mixed>>  $events
     * @return array{created: int}
     */
    public function importBusyEvents(User $provider, array $events): array
    {
        return DB::transaction(function () use ($provider, $events) {
            AvailabilityException::query()
                ->where('provider_user_id', $provider->id)
                ->where('metadata->source', 'google')
                ->delete();

            $created = 0;
            foreach ($events as $event) {
                $created += $this->mapEventToExceptions($provider, $event);
            }

            return ['created' => $created];
        });
    }

    /** @param array<string, mixed> $event */
    protected function mapEventToExceptions(User $provider, array $event): int
    {
        $extId = $event['external_id'] ?? null;
        $summary = (string) ($event['summary'] ?? 'Google Calendar');
        $meta = ['source' => 'google', 'external_id' => $extId];
        $start = CarbonImmutable::parse((string) $event['start']);
        $end = CarbonImmutable::parse((string) ($event['end'] ?? $event['start']));
        $allDay = (bool) ($event['all_day'] ?? false);

        // Événement horaire sur une seule journée → exception PARTIAL (plage soustraite).
        if (! $allDay && $start->isSameDay($end) && $end->gt($start)) {
            AvailabilityException::create([
                'provider_user_id' => $provider->id,
                'date' => $start->format('Y-m-d'),
                'exception_type' => AvailabilityException::TYPE_PARTIAL,
                'start_time' => $start->format('H:i:s'),
                'end_time' => $end->format('H:i:s'),
                'reason' => $summary,
                'metadata' => $meta,
            ]);

            return 1;
        }

        // Journée entière / multi-jours → CLOSED pour chaque jour. Pour un all-day,
        // la fin Google est exclusive (jour suivant), donc on s'arrête la veille.
        $firstDay = $start->startOfDay();
        $lastDay = ($allDay ? $end->subDay() : $end)->startOfDay();
        if ($lastDay->lt($firstDay)) {
            $lastDay = $firstDay;
        }

        $count = 0;
        for ($day = $firstDay; $day->lte($lastDay); $day = $day->addDay()) {
            AvailabilityException::create([
                'provider_user_id' => $provider->id,
                'date' => $day->format('Y-m-d'),
                'exception_type' => AvailabilityException::TYPE_CLOSED,
                'reason' => $summary,
                'metadata' => $meta,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Détecte si un créneau de réservation chevauche un blocage Google du prestataire.
     */
    public function hasConflict(User $provider, Booking $booking): bool
    {
        $date = $booking->date;
        $heure = $booking->heure;
        if (! $date || ! $heure) {
            return false;
        }

        $day = $date->format('Y-m-d');
        $start = CarbonImmutable::parse($day.' '.$heure);
        $end = $start->addMinutes((int) ($booking->duree_estimee ?: 60));

        $exceptions = AvailabilityException::query()
            ->where('provider_user_id', $provider->id)
            ->where('metadata->source', 'google')
            ->whereDate('date', $day)
            ->get();

        foreach ($exceptions as $ex) {
            if ($ex->exception_type === AvailabilityException::TYPE_CLOSED) {
                return true;
            }
            if ($ex->exception_type === AvailabilityException::TYPE_PARTIAL && $ex->start_time && $ex->end_time) {
                $blockStart = CarbonImmutable::parse($day.' '.$ex->start_time);
                $blockEnd = CarbonImmutable::parse($day.' '.$ex->end_time);
                if ($start->lt($blockEnd) && $end->gt($blockStart)) {
                    return true;
                }
            }
        }

        return false;
    }
}

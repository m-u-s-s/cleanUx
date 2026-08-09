<?php

namespace App\Services\Dispatch;

use App\Models\Booking;
use App\Models\MissionAssignment;
use App\Models\ServiceCatalog;
use App\Models\User;

/**
 * CE QUE LE PRESTATAIRE LIT DANS SA MODALE — écrit une seule fois.
 *
 * La même charge utile part en temps réel, en push, et ressort de l'inbox : trois formes
 * différentes du même écran feraient afficher trois prix différents selon le canal par lequel
 * l'offre est arrivée, et c'est le genre d'écart qui se découvre en production.
 *
 * L'ADRESSE EST APPROXIMATIVE tant que l'offre n'est pas acceptée. La rue et le numéro exacts
 * n'apparaissent qu'après : une offre refusée ne doit pas laisser l'adresse complète d'un client
 * chez quelqu'un qui n'ira jamais.
 */
class OfferPayloadBuilder
{
    /** @return array<string, mixed> */
    public function build(MissionAssignment $assignment, ?int $distanceM = null): array
    {
        $mission = $assignment->mission;
        $booking = $this->bookingOf($assignment);

        $expiresAt = $assignment->expires_at;

        return [
            'assignment_id' => (int) $assignment->id,
            'mission_id' => (int) $assignment->mission_id,
            'booking_id' => $booking?->id,
            'booking_mode' => $booking === null ? 'scheduled' : ($booking->booking_mode ?? 'scheduled'),
            'trade_name' => $this->tradeName($booking) ?? $this->serviceName($booking),
            'service_name' => $this->serviceName($booking) ?? $this->tradeName($booking),
            'client_name' => $this->clientName($booking),
            // Ville et code postal seulement : l'adresse exacte est le prix de l'acceptation.
            'approximate_address' => trim(sprintf(
                '%s %s',
                (string) ($booking === null ? '' : ($booking->postal_code ?? '')),
                (string) ($booking === null ? '' : ($booking->city ?? '')),
            )) ?: null,
            'city' => $booking?->city,
            'postal_code' => $booking?->postal_code,
            'scheduled_at' => $booking?->scheduled_at?->toIso8601String(),
            'estimated_duration_minutes' => $mission?->estimated_duration_minutes,
            'payout_cents' => $this->payoutCents($booking),
            'distance_m' => $distanceM,
            'distance_km' => $distanceM !== null ? round($distanceM / 1000, 1) : null,
            'latitude' => $this->toFloat($mission?->getAttribute('destination_lat') ?? $booking?->getAttribute('destination_lat')),
            'longitude' => $this->toFloat($mission?->getAttribute('destination_lng') ?? $booking?->getAttribute('destination_lng')),
            /*
             * L'HORLOGE FAIT AUTORITÉ CÔTÉ SERVEUR. `expires_at` voyage en ISO-8601 et
             * `ttl_seconds` n'est qu'un repli d'affichage : un téléphone dont l'heure est fausse de
             * dix minutes afficherait sinon une offre déjà morte ou éternelle.
             */
            'expires_at' => $expiresAt?->toIso8601String(),
            'ttl_seconds' => $expiresAt ? max(0, (int) now()->diffInSeconds($expiresAt, false)) : null,
            'sent_at' => now()->toIso8601String(),
        ];
    }

    /**
     * LA RESERVATION DERRIERE LA MISSION — trois chemins, un seul ordre.
     *
     * `missions` porte historiquement DEUX cles vers la meme table : `booking_id` et
     * `rendez_vous_id`, selon le chemin qui l'a creee. Lire l'une sans l'autre rendait des offres
     * sans adresse ni prix — la modale s'ouvrait sur des tirets.
     */
    protected function bookingOf(MissionAssignment $assignment): ?Booking
    {
        $mission = $assignment->mission;

        if (! $mission) {
            return null;
        }

        return $mission->bookingViaBookingId ?? $mission->rendezVous ?? $mission->booking;
    }

    protected function tradeName(?Booking $booking): ?string
    {
        return $booking?->resolveTrade()?->name;
    }

    /** Le service au catalogue, quand la reservation en porte un — les archives en ont, pas le moteur de commande. */
    protected function serviceName(?Booking $booking): ?string
    {
        $catalogue = $booking?->getRelationValue('serviceCatalog');

        return $catalogue instanceof ServiceCatalog ? $catalogue->name : null;
    }

    /**
     * Le prénom du client, ou rien.
     *
     * Vérifié par `instanceof` plutôt que par accès enchaîné : une réservation orpheline — client
     * supprimé au titre du droit à l'oubli — ferait tomber la construction de l'offre, et donc TOUT
     * le dispatch, pour un libellé d'affichage.
     */
    protected function clientName(?Booking $booking): ?string
    {
        $client = $booking?->getRelationValue('customer');

        return $client instanceof User ? $client->name : null;
    }

    protected function payoutCents(?Booking $booking): ?int
    {
        if (! $booking) {
            return null;
        }

        $provider = $booking->getAttribute('provider_amount_cents');

        if ($provider !== null) {
            return (int) $provider;
        }

        $estime = $booking->getAttribute('estimated_price');

        return $estime !== null ? (int) round((float) $estime * 100) : null;
    }

    protected function toFloat(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}

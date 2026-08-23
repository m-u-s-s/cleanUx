<?php

namespace App\Services\Client;

use App\Models\Booking;
use App\Models\TripTrackingSession;
use App\Services\TripTracking\TripTrackingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/** PARTAGER LE SUIVI D'UNE INTERVENTION (E3) — le patron « suivez ma course ». LE CAS QUE ÇA RÈGLE. */
class SharedTrackingService
{
    /** Combien de temps un lien reste valable. */
    public const VALIDITE_HEURES = 12;

    /** Le lien à envoyer par SMS ou par message. */
    public function lienPour(Booking $booking): string
    {
        return URL::temporarySignedRoute(
            'tracking.shared',
            Carbon::now()->addHours(self::VALIDITE_HEURES),
            ['booking' => $booking->id],
        );
    }

    /**
     * Ce que la page publique a le droit d'afficher. VOLONTAIREMENT PAUVRE.
     *
     * @return array<string, mixed>
     */
    public function apercu(Booking $booking): array
    {
        $session = app(TripTrackingService::class)->activeSessionForBooking((int) $booking->id);

        return [
            'reference' => $booking->booking_reference,
            // Le PRÉNOM seul du prestataire : « Karim arrive » rassure, un nom complet est une
            // donnée personnelle qu'on n'a aucune raison de diffuser à un tiers.
            'provider_first_name' => $this->prenomDuPrestataire($booking),
            'scheduled_at' => $booking->scheduled_at?->toIso8601String(),
            'status' => (string) $booking->status,
            // La ville, pas l'adresse : le destinataire sait déjà où il habite, et un lien qui
            // circule ne doit pas diffuser une adresse.
            'city' => $booking->city ?: $booking->ville,
            'beneficiary_name' => $booking->beneficiary_name,
            'tracking' => $session === null ? null : $this->positionLisible($session),
            'expires_in_hours' => self::VALIDITE_HEURES,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function positionLisible(TripTrackingSession $session): array
    {
        return [
            'status' => $session->status,
            'lat' => $session->last_lat !== null ? (float) $session->last_lat : null,
            'lng' => $session->last_lng !== null ? (float) $session->last_lng : null,
            // LA DESTINATION ET LE TRACÉ — pauvres, comme le reste de cette page.
            'destination' => [
                'lat' => $session->destination_lat !== null ? (float) $session->destination_lat : null,
                'lng' => $session->destination_lng !== null ? (float) $session->destination_lng : null,
            ],
            'route' => app(TripTrackingService::class)->routePayload($session),
            'eta_seconds' => $session->current_eta_seconds,
            'arrived_at' => $session->arrived_at?->toIso8601String(),
            'in_mission_at' => $session->in_mission_at?->toIso8601String(),
            'last_ping_at' => $session->last_ping_at?->toIso8601String(),
        ];
    }

    protected function prenomDuPrestataire(Booking $booking): ?string
    {
        $nom = $booking->employe?->name;

        if (! is_string($nom) || trim($nom) === '') {
            return null;
        }

        return explode(' ', trim($nom))[0];
    }
}

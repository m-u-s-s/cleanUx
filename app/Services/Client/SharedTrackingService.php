<?php

namespace App\Services\Client;

use App\Models\Booking;
use App\Models\TripTrackingSession;
use App\Services\TripTracking\TripTrackingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * PARTAGER LE SUIVI D'UNE INTERVENTION (E3) — le patron « suivez ma course ».
 *
 * LE CAS QUE ÇA RÈGLE. Quelqu'un commande un ménage pour sa mère et n'est pas sur place : il veut
 * qu'elle sache quand sonner, et elle n'a pas de compte. Aujourd'hui il lui téléphone, ou lui écrit
 * « il arrive vers 14 h » — et rappelle vingt minutes plus tard parce que le prestataire est pris
 * dans un embouteillage. Le suivi existe déjà et ne sort pas de l'écran de celui qui a payé.
 *
 * LE LIEN EST SIGNÉ ET EXPIRANT, jamais devinable. Un identifiant de réservation dans une URL
 * publique se devine en comptant ; un lien signé ne s'accepte que tel qu'il a été émis, et cesse de
 * fonctionner passé l'échéance. La durée est courte parce que ce lien montre où se trouve une
 * personne en temps réel : le laisser valable un mois en ferait un traceur.
 *
 * ET IL NE MONTRE QUE CE QU'IL FAUT. Ni l'adresse complète, ni le montant, ni le nom du client, ni
 * le téléphone du prestataire : une position, une heure d'arrivée estimée, un état. Le destinataire
 * a besoin de savoir QUAND, pas QUI paye combien.
 */
class SharedTrackingService
{
    /**
     * Combien de temps un lien reste valable.
     *
     * DOUZE HEURES, et pas davantage : ce lien montre une position en temps réel. Un partage qui
     * survit à l'intervention devient un traceur, et personne ne pense à le révoquer.
     */
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
     * Ce que la page publique a le droit d'afficher.
     *
     * VOLONTAIREMENT PAUVRE. Le destinataire du lien n'est pas le client : il n'a pas à connaître le
     * montant, l'adresse exacte, ni l'identité du prestataire. Une position et une heure suffisent
     * à ce pour quoi le lien a été envoyé.
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
            /*
             * LA DESTINATION ET LE TRACÉ — pauvres, comme le reste de cette page.
             *
             * Ce sont des COORDONNÉES, pas une adresse : le destinataire voit vers où la voiture se
             * dirige sans jamais lire une rue ni un numéro. C'est exactement ce que montre le lien
             * de suivi d'un taxi, et c'est ce qui rend la page utile plutôt que rassurante en
             * paroles.
             *
             * Elles viennent de la SESSION : sur une course, elle bascule d'elle-même vers le point
             * de dépose quand le passager monte.
             */
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

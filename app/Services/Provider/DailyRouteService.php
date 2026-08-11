<?php

namespace App\Services\Provider;

use App\Models\Mission;
use App\Models\User;
use App\Services\Geo\GeoDistanceService;
use Illuminate\Support\Carbon;

/**
 * MA FICHE DU JOUR ET LA TOURNÉE (E17 + E34).
 *
 * CE QUI SE PASSE AUJOURD'HUI. Un prestataire a quatre interventions dans la journée et les découvre
 * dans une liste triée par heure. Il ne sait pas combien de temps il lui faut entre la deuxième et
 * la troisième, ni si l'ordre proposé lui fait traverser la ville deux fois. Il l'apprend en le
 * faisant, et arrive en retard à la troisième.
 *
 * L'HEURE PRÉVUE FAIT FOI, TOUJOURS. La tournée n'est pas une optimisation libre : un client attend
 * à 14 h, et le prestataire ne peut pas décider d'arriver à 16 h parce que c'est plus court. On
 * ordonne donc par HORAIRE, et le calcul de trajet sert à dire si l'enchaînement TIENT — pas à
 * réordonner.
 *
 * C'EST LA DIFFÉRENCE ENTRE UN OUTIL UTILE ET UN OUTIL QU'ON DÉSACTIVE. Un optimiseur qui propose de
 * décaler des rendez-vous pris ne sert à personne ; un écran qui dit « vous avez 25 minutes de
 * trajet et 20 minutes de battement » permet de prévenir avant, ce qui change tout pour le client.
 *
 * LA DISTANCE EST À VOL D'OISEAU, ET C'EST DIT. Sans service de routage, prétendre à une durée de
 * trajet exacte serait mentir : on majore prudemment et on annonce l'approximation. Un temps
 * sous-estimé ferait rater le rendez-vous suivant.
 */
class DailyRouteService
{
    /**
     * Vitesse moyenne retenue en ville, en km/h.
     *
     * DÉLIBÉRÉMENT BASSE : surestimer le temps de trajet fait partir plus tôt, ce qui se rattrape en
     * attendant cinq minutes. Le sous-estimer fait arriver en retard, ce qui ne se rattrape pas.
     */
    public const VITESSE_KMH = 22.0;

    /**
     * Le coefficient qui transforme la distance à vol d'oiseau en distance routière.
     *
     * Une ville n'est pas un plan : 1,4 est l'ordre de grandeur admis pour un tissu urbain dense.
     */
    public const COEFFICIENT_ROUTIER = 1.4;

    /**
     * La journée d'un prestataire, ordonnée et chaînée.
     *
     * @return array<string, mixed>
     */
    public function pourLaJournee(User $prestataire, ?Carbon $jour = null): array
    {
        $jour ??= Carbon::now();

        $missions = Mission::query()
            ->where('lead_provider_user_id', $prestataire->id)
            ->whereNotNull('planned_start_at')
            ->whereBetween('planned_start_at', [$jour->copy()->startOfDay(), $jour->copy()->endOfDay()])
            ->whereNotIn('status', ['cancelled', 'annule'])
            ->with('booking:id,booking_reference,address,destination_lat,destination_lng,scheduled_at')
            // L'HEURE PRÉVUE FAIT FOI : un client attend à 14 h, et on ne réordonne pas des
            // rendez-vous pris pour raccourcir un trajet.
            ->orderBy('planned_start_at')
            ->get();

        $etapes = [];
        $precedente = null;
        $distanceTotale = 0.0;

        foreach ($missions as $mission) {
            $trajet = $this->trajetDepuis($precedente, $mission);

            $etapes[] = [
                'mission_id' => $mission->id,
                'booking_reference' => $mission->booking?->booking_reference,
                'address' => $mission->booking?->address,
                'planned_start_at' => $mission->planned_start_at?->toIso8601String(),
                'lat' => $mission->booking?->destination_lat,
                'lng' => $mission->booking?->destination_lng,
                'status' => (string) $mission->status,
                // `null` pour la première : il n'y a pas de trajet avant la première étape, et
                // afficher « 0 min » laisserait croire qu'elle est à côté de chez soi.
                'travel_km' => $trajet['km'],
                'travel_minutes' => $trajet['minutes'],
                /*
                 * LE BATTEMENT EST LE CHIFFRE QUI COMPTE. Négatif, l'enchaînement ne tient pas :
                 * c'est ce qu'il faut savoir la veille, pas en route.
                 */
                'slack_minutes' => $trajet['slack_minutes'],
                'is_tight' => $trajet['slack_minutes'] !== null && $trajet['slack_minutes'] < 0,
            ];

            $distanceTotale += (float) ($trajet['km'] ?? 0);
            $precedente = $mission;
        }

        return [
            'date' => $jour->toDateString(),
            'missions_count' => count($etapes),
            'total_travel_km' => round($distanceTotale, 1),
            // Le nombre d'enchaînements qui ne tiennent pas : le seul chiffre qui appelle une
            // action, et donc celui qu'on met en avant.
            'tight_transitions' => count(array_filter($etapes, fn (array $e) => $e['is_tight'])),
            'steps' => $etapes,
            // L'approximation est ANNONCÉE : prétendre à une durée exacte sans service de routage
            // serait mentir, et un temps sous-estimé ferait rater le rendez-vous suivant.
            'is_estimate' => true,
            'assumed_speed_kmh' => self::VITESSE_KMH,
        ];
    }

    /**
     * @return array{km: float|null, minutes: int|null, slack_minutes: int|null}
     */
    protected function trajetDepuis(?Mission $precedente, Mission $mission): array
    {
        $depart = $precedente?->booking;
        $arrivee = $mission->booking;

        if ($depart === null || $arrivee === null) {
            return ['km' => null, 'minutes' => null, 'slack_minutes' => null];
        }

        $lat1 = $depart->destination_lat;
        $lng1 = $depart->destination_lng;
        $lat2 = $arrivee->destination_lat;
        $lng2 = $arrivee->destination_lng;

        /*
         * `is_numeric` ET NON `=== null`. Ces colonnes sont déclarées NON nullables et rendent une
         * CHAÎNE : une réservation sans coordonnées porte une chaîne vide, pas `null`. Comparer à
         * `null` était donc toujours faux, et le garde-fou ne gardait rien — on aurait calculé une
         * distance depuis le point zéro de l'Atlantique.
         *
         * SANS COORDONNÉES, ON NE DEVINE PAS : rendre `null` fait afficher « — », tandis
         * qu'inventer une distance ferait planifier une journée sur un chiffre faux.
         */
        if (! is_numeric($lat1) || ! is_numeric($lng1) || ! is_numeric($lat2) || ! is_numeric($lng2)) {
            return ['km' => null, 'minutes' => null, 'slack_minutes' => null];
        }

        $km = round(
            /*
             * `haversineKm` ET NON `drivingDistanceKm` : le second appelle un service externe, et
             * une fiche du jour qui dépend d'un tiers ne s'affiche pas le matin où ce tiers est en
             * panne. On majore une distance à vol d'oiseau, et on l'annonce.
             */
            app(GeoDistanceService::class)->haversineKm((float) $lat1, (float) $lng1, (float) $lat2, (float) $lng2)
                * self::COEFFICIENT_ROUTIER,
            1,
        );

        $minutes = (int) ceil($km / self::VITESSE_KMH * 60);

        $slack = null;

        if ($precedente->planned_end_at !== null && $mission->planned_start_at !== null) {
            $disponible = (int) $precedente->planned_end_at->diffInMinutes($mission->planned_start_at, false);
            $slack = $disponible - $minutes;
        }

        return ['km' => $km, 'minutes' => $minutes, 'slack_minutes' => $slack];
    }
}

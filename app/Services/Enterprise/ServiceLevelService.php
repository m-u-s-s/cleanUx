<?php

namespace App\Services\Enterprise;

use App\Models\Booking;
use App\Models\TripTrackingSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * LE TABLEAU DE SERVICE D'UNE ENTREPRISE CLIENTE (E9).
 *
 * LA QUESTION À LAQUELLE IL RÉPOND. « Est-ce que ce prestataire tient ses engagements ? » — celle
 * qu'on pose au moment de renouveler un contrat, et à laquelle personne ne sait répondre autrement
 * qu'au ressenti. Les données existent toutes : les missions, le suivi qui date les arrivées, le
 * moteur d'annulation. Aucune ne se lit côté client entreprise.
 *
 * TROIS TAUX, ET C'EST ASSEZ. Réalisation, ponctualité, annulation. Un tableau plus fourni décrit
 * un contrat de niveau de service d'infogérance, pas la relation d'une société de vingt personnes
 * avec son prestataire de nettoyage. Ce qu'on n'utilise pas ne se regarde pas.
 *
 * LA PONCTUALITÉ SE MESURE SUR L'ARRIVÉE RELEVÉE, jamais sur un statut. Une mission qu'on a oublié
 * de démarrer n'est pas un retard, et un démarrage tardif dans l'application n'en est pas un non
 * plus : seule `arrived_at`, posée par la géo-barrière, dit où était la personne et quand.
 *
 * LES MISSIONS SANS ARRIVÉE RELEVÉE SONT COMPTÉES À PART, jamais fondues. Les compter comme des
 * retards punirait un GPS coupé ; comme des arrivées à l'heure, l'inverse. On les annonce.
 */
class ServiceLevelService
{
    /** Au-delà, l'arrivée n'est plus « à l'heure ». */
    public const TOLERANCE_RETARD_MINUTES = 15;

    /**
     * Le tableau d'une période, ventilé par local.
     *
     * Rendu comme une LISTE : c'est une charge utile, lue une fois par l'écran ou sérialisée par
     * l'API. Rien ici n'appelle de chaînage.
     *
     * @return list<array<string, mixed>>
     */
    public function parLocal(int $organisationId, Carbon $debut, Carbon $fin): array
    {
        $reservations = Booking::query()
            ->where('customer_organization_id', $organisationId)
            ->whereBetween('scheduled_at', [$debut, $fin])
            ->with('organizationSite:id,name')
            ->get(['id', 'organization_site_id', 'status', 'scheduled_at', 'devis_estime']);

        $arrivees = $this->arriveesRelevees($reservations->pluck('id')->all());

        return $reservations
            ->groupBy(fn (Booking $booking) => $booking->organization_site_id ?? 0)
            ->map(function (Collection $groupe, $siteId) use ($arrivees) {
                $total = $groupe->count();

                $realisees = $groupe->filter(fn (Booking $b) => in_array(
                    $b->status,
                    ['completed', 'termine', 'terminee', 'done'],
                    true,
                ))->count();

                $annulees = $groupe->filter(fn (Booking $b) => in_array(
                    $b->status,
                    ['annule', 'cancelled', 'refused', 'refuse'],
                    true,
                ))->count();

                [$aLHeure, $mesurees] = $this->ponctualite($groupe, $arrivees);

                return [
                    'site_id' => (int) $siteId,
                    'site_name' => $groupe->first()?->organizationSite?->name,
                    'bookings_count' => $total,
                    'completed_count' => $realisees,
                    'cancelled_count' => $annulees,
                    'completion_rate' => $this->taux($realisees, $total),
                    'cancellation_rate' => $this->taux($annulees, $total),
                    // `null` quand rien n'a pu être mesuré : un taux de 0 % se lirait comme
                    // « personne n'arrive à l'heure », ce qui n'est pas ce qu'on sait.
                    'punctuality_rate' => $mesurees > 0 ? $this->taux($aLHeure, $mesurees) : null,
                    'punctuality_measured_on' => $mesurees,
                    // Annoncées, jamais fondues : les compter comme des retards punirait un GPS
                    // coupé ; comme des arrivées à l'heure, l'inverse.
                    'without_arrival_data' => $total - $mesurees,
                    'committed_cents' => (int) round($groupe->sum('devis_estime') * 100),
                ];
            })
            ->sortByDesc('bookings_count')
            ->values()
            ->all();
    }

    /**
     * Le résumé de toute la société — la ligne qu'on regarde en premier.
     *
     * @return array<string, mixed>
     */
    public function resume(int $organisationId, Carbon $debut, Carbon $fin): array
    {
        $lignes = collect($this->parLocal($organisationId, $debut, $fin));

        $total = (int) $lignes->sum('bookings_count');
        $mesurees = (int) $lignes->sum('punctuality_measured_on');
        $aLHeure = (int) $lignes->sum(
            fn (array $ligne) => (int) round(($ligne['punctuality_rate'] ?? 0) / 100 * $ligne['punctuality_measured_on']),
        );

        return [
            'from' => $debut->toDateString(),
            'to' => $fin->toDateString(),
            'bookings_count' => $total,
            'completion_rate' => $this->taux((int) $lignes->sum('completed_count'), $total),
            'cancellation_rate' => $this->taux((int) $lignes->sum('cancelled_count'), $total),
            'punctuality_rate' => $mesurees > 0 ? $this->taux($aLHeure, $mesurees) : null,
            'without_arrival_data' => (int) $lignes->sum('without_arrival_data'),
        ];
    }

    /**
     * @param  Collection<int, Booking>  $groupe
     * @param  array<int, Carbon>  $arrivees
     * @return array{0: int, 1: int}  [arrivées à l'heure, arrivées mesurées]
     */
    protected function ponctualite(Collection $groupe, array $arrivees): array
    {
        $aLHeure = 0;
        $mesurees = 0;

        foreach ($groupe as $booking) {
            $arrivee = $arrivees[$booking->id] ?? null;

            if ($arrivee === null || $booking->scheduled_at === null) {
                continue;
            }

            $mesurees++;

            if ($booking->scheduled_at->diffInMinutes($arrivee, false) <= self::TOLERANCE_RETARD_MINUTES) {
                $aLHeure++;
            }
        }

        return [$aLHeure, $mesurees];
    }

    /**
     * @param  array<int, int>  $bookingIds
     * @return array<int, Carbon>
     */
    protected function arriveesRelevees(array $bookingIds): array
    {
        if ($bookingIds === []) {
            return [];
        }

        return TripTrackingSession::query()
            ->whereIn('booking_id', $bookingIds)
            ->whereNotNull('arrived_at')
            ->get(['booking_id', 'arrived_at'])
            ->mapWithKeys(fn (TripTrackingSession $session) => [
                (int) $session->booking_id => Carbon::instance($session->arrived_at),
            ])
            ->all();
    }

    protected function taux(int $part, int $total): float
    {
        return $total > 0 ? round($part / $total * 100, 1) : 0.0;
    }
}

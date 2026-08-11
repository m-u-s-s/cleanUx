<?php

namespace App\Services\Client;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * LE BUDGET MAISON D'UN CLIENT (E4).
 *
 * TOUT EST DÉJÀ EN BASE, et personne ne le voit. Un client reçoit ses factures une par une et n'a
 * aucun moyen de répondre à la seule question qu'il se pose vraiment : « combien est-ce que je
 * dépense en entretien, et est-ce que ça augmente ». C'est cette question qui décide de passer à un
 * abonnement, d'espacer les interventions, ou de renoncer.
 *
 * L'ENGAGÉ, PAS LE FACTURÉ. Le montant retenu est celui de la réservation — devis accepté — et non
 * une facture émise : une intervention prévue la semaine prochaine compte déjà dans le budget du
 * mois, parce que c'est ainsi qu'on raisonne quand on regarde ce qu'on va dépenser. Les annulées
 * sont écartées : elles n'ont rien coûté.
 *
 * LE COMPARATIF ABONNEMENT / À LA DEMANDE EST LE SEUL CHIFFRE QUI SERVE À DÉCIDER. Le reste
 * documente ; celui-ci répond. On le rend même quand une des deux séries est vide — « vous n'avez
 * aucun abonnement » est une réponse utile, un champ absent ne l'est pas.
 */
class HomeBudgetService
{
    /**
     * Ce qu'un client dépense, par mois et par métier.
     *
     * @return array<string, mixed>
     */
    public function pour(User $client, ?Carbon $depuis = null, ?Carbon $jusqua = null): array
    {
        $depuis ??= Carbon::now()->subYear()->startOfMonth();
        $jusqua ??= Carbon::now()->endOfMonth();

        $reservations = Booking::query()
            ->where('client_id', $client->id)
            // Une annulée n'a rien coûté : la compter gonflerait le budget d'un montant que
            // personne n'a payé.
            ->whereNotIn('status', ['annule', 'cancelled', 'refused', 'refuse'])
            ->whereBetween('created_at', [$depuis, $jusqua])
            ->with('trade:id,name')
            ->get(['id', 'trade_id', 'devis_estime', 'created_at', 'is_recurrent', 'recurring_series_id']);

        $parMois = $reservations
            ->groupBy(fn (Booking $booking) => $booking->created_at?->format('Y-m') ?? 'inconnu')
            ->map(fn ($lignes, $mois) => [
                'month' => $mois,
                'bookings_count' => $lignes->count(),
                'total_cents' => $this->totalCents($lignes),
            ])
            ->sortKeys()
            ->values()
            ->all();

        $parMetier = $reservations
            ->groupBy(fn (Booking $booking) => $booking->trade->name ?? 'Autre')
            ->map(fn ($lignes, $metier) => [
                'trade' => $metier,
                'bookings_count' => $lignes->count(),
                'total_cents' => $this->totalCents($lignes),
            ])
            ->sortByDesc('total_cents')
            ->values()
            ->all();

        $totalCents = $this->totalCents($reservations);
        $moisComptes = max(1, count($parMois));

        return [
            'from' => $depuis->toDateString(),
            'to' => $jusqua->toDateString(),
            'bookings_count' => $reservations->count(),
            'total_cents' => $totalCents,
            // La moyenne se calcule sur les mois OÙ IL S'EST PASSÉ QUELQUE CHOSE, pas sur la
            // fenêtre : diviser par douze un client arrivé en octobre lui montrerait une moyenne
            // qu'il ne reconnaît pas.
            'monthly_average_cents' => (int) round($totalCents / $moisComptes),
            'by_month' => $parMois,
            'by_trade' => $parMetier,
            'subscription_vs_on_demand' => $this->comparatif($reservations),
        ];
    }

    /**
     * ABONNEMENT CONTRE À LA DEMANDE — le seul chiffre qui serve à décider.
     *
     * Rendu MÊME QUAND UNE SÉRIE EST VIDE : « vous n'avez aucun abonnement » est une réponse utile,
     * un champ absent ne l'est pas — l'écran afficherait un trou que le client lirait comme un bug.
     *
     * @param  \Illuminate\Support\Collection<int, Booking>  $reservations
     * @return array<string, mixed>
     */
    protected function comparatif(\Illuminate\Support\Collection $reservations): array
    {
        /*
         * DEUX SIGNAUX POUR LA MÊME NOTION, et il faut les deux. `is_recurrent` marque la
         * réservation créée comme récurrente ; `recurring_series_id` rattache les OCCURRENCES
         * engendrées ensuite, qui ne portent pas toujours le drapeau. N'en lire qu'un ferait
         * basculer la moitié d'un abonnement du côté « à la demande », et le comparatif dirait
         * exactement le contraire de la vérité.
         */
        [$recurrentes, $ponctuelles] = $reservations->partition(
            fn (Booking $booking) => (bool) $booking->is_recurrent || $booking->recurring_series_id !== null,
        );

        return [
            'subscription' => [
                'bookings_count' => $recurrentes->count(),
                'total_cents' => $this->totalCents($recurrentes),
                'average_cents' => $this->moyenneCents($recurrentes),
            ],
            'on_demand' => [
                'bookings_count' => $ponctuelles->count(),
                'total_cents' => $this->totalCents($ponctuelles),
                'average_cents' => $this->moyenneCents($ponctuelles),
            ],
        ];
    }

    /** @param  \Illuminate\Support\Collection<int, Booking>  $lignes */
    protected function totalCents(\Illuminate\Support\Collection $lignes): int
    {
        return (int) $lignes->sum(fn (Booking $booking) => (int) round(((float) ($booking->devis_estime ?? 0)) * 100));
    }

    /** @param  \Illuminate\Support\Collection<int, Booking>  $lignes */
    protected function moyenneCents(\Illuminate\Support\Collection $lignes): int
    {
        return $lignes->isEmpty() ? 0 : (int) round($this->totalCents($lignes) / $lignes->count());
    }
}

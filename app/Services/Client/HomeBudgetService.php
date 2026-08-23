<?php

namespace App\Services\Client;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** LE BUDGET MAISON D'UN CLIENT (E4). TOUT EST DÉJÀ EN BASE, et personne ne le voit. */
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
     * @param  Collection<int, Booking>  $reservations
     * @return array<string, mixed>
     */
    protected function comparatif(Collection $reservations): array
    {
        // DEUX SIGNAUX POUR LA MÊME NOTION, et il faut les deux.
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

    /** @param  Collection<int, Booking>  $lignes */
    protected function totalCents(Collection $lignes): int
    {
        return (int) $lignes->sum(fn (Booking $booking) => (int) round(((float) ($booking->devis_estime ?? 0)) * 100));
    }

    /** @param  Collection<int, Booking>  $lignes */
    protected function moyenneCents(Collection $lignes): int
    {
        return $lignes->isEmpty() ? 0 : (int) round($this->totalCents($lignes) / $lignes->count());
    }
}

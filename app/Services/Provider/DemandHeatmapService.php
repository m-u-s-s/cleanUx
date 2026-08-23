<?php

namespace App\Services\Provider;

use App\Models\AsapDispatchRequest;
use App\Models\Booking;
use App\Models\ServiceZone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** OÙ ME PLACER, ET À QUELLE HEURE (E12). */
class DemandHeatmapService
{
    /** Les tranches horaires, en heures pleines de début. */
    private const TRANCHES = [6, 8, 10, 12, 14, 16, 18, 20];

    /**
     * La demande observée, par zone et par tranche.
     *
     * @return list<array<string, mixed>>
     */
    public function pourLaPeriode(?Carbon $depuis = null, ?Carbon $jusqua = null, ?int $tradeId = null): array
    {
        $depuis ??= Carbon::now()->subDays(28);
        $jusqua ??= Carbon::now();

        $jours = max(1, (int) $depuis->diffInDays($jusqua));

        $observations = $this->recherchesImmediates($depuis, $jusqua, $tradeId)
            ->concat($this->reservationsPlanifiees($depuis, $jusqua, $tradeId));

        $zones = ServiceZone::query()->pluck('name', 'id');

        return $observations
            ->groupBy(fn (array $ligne) => $ligne['zone_id'].'|'.$ligne['slot'])
            ->map(function ($groupe) use ($zones, $jours) {
                $premiere = $groupe->first();

                return [
                    'zone_id' => $premiere['zone_id'],
                    'zone_name' => $zones[$premiere['zone_id']] ?? 'Hors zone',
                    // La tranche, pas l'heure exacte : « entre 8 h et 10 h » est une décision,
                    // « à 8 h 37 » est du bruit.
                    'slot' => $premiere['slot'],
                    'slot_label' => sprintf('%02dh–%02dh', $premiere['slot'], $premiere['slot'] + 2),
                    'demand_count' => $groupe->count(),
                    'immediate_count' => $groupe->where('kind', 'immediate')->count(),
                    'scheduled_count' => $groupe->where('kind', 'scheduled')->count(),
                    // Sur combien de jours l'observation porte : sans ça, un pic isolé se lit
                    // comme une tendance.
                    'days_observed' => $jours,
                    'per_day' => round($groupe->count() / $jours, 2),
                ];
            })
            ->sortByDesc('demand_count')
            ->values()
            ->all();
    }

    /**
     * Les recherches immédiates — où la demande arrive MAINTENANT.
     *
     * @return Collection<int, array{zone_id: int<0, max>, slot: int, kind: 'immediate'}>
     */
    protected function recherchesImmediates(Carbon $depuis, Carbon $jusqua, ?int $tradeId): Collection
    {
        return AsapDispatchRequest::query()
            ->whereBetween('created_at', [$depuis, $jusqua])
            ->when($tradeId, fn ($q) => $q->where('trade_id', $tradeId))
            ->with('booking:id,service_zone_id')
            ->get(['id', 'trade_id', 'booking_id', 'created_at'])
            ->map(fn (AsapDispatchRequest $recherche) => [
                'zone_id' => (int) ($recherche->booking->service_zone_id ?? 0),
                // `Carbon::instance()` : les colonnes datées rendent un `Carbon\Carbon`, et la
                // méthode attend un `Illuminate\Support\Carbon`. Ils se ressemblent assez pour
                // passer inaperçus, et assez peu pour que l'analyse statique refuse.
                'slot' => $this->tranche(Carbon::instance($recherche->created_at ?? now())),
                'kind' => 'immediate',
            ]);
    }

    /**
     * Les réservations planifiées — où la demande arrivera.
     *
     * @return Collection<int, array{zone_id: int<0, max>, slot: int, kind: 'scheduled'}>
     */
    protected function reservationsPlanifiees(Carbon $depuis, Carbon $jusqua, ?int $tradeId): Collection
    {
        return Booking::query()
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$depuis, $jusqua])
            ->when($tradeId, fn ($q) => $q->where('trade_id', $tradeId))
            ->whereNotIn('status', ['annule', 'cancelled'])
            ->get(['id', 'service_zone_id', 'scheduled_at'])
            ->map(fn (Booking $booking) => [
                'zone_id' => (int) ($booking->service_zone_id ?? 0),
                'slot' => $this->tranche(Carbon::instance($booking->scheduled_at ?? now())),
                'kind' => 'scheduled',
            ]);
    }

    /** La tranche de deux heures qui contient ce moment. */
    protected function tranche(?Carbon $moment): int
    {
        if ($moment === null) {
            return self::TRANCHES[0];
        }

        $heure = (int) $moment->hour;

        // On redescend à la tranche ouverte la plus proche : une demande à 5 h du matin compte
        // dans la première tranche plutôt que de disparaître.
        $retenue = self::TRANCHES[0];

        foreach (self::TRANCHES as $debut) {
            if ($heure >= $debut) {
                $retenue = $debut;
            }
        }

        return $retenue;
    }
}

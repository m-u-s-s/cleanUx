<?php

namespace App\Services\Provider;

use App\Models\MissionAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** LES STATISTIQUES D'OFFRES D'UN PRESTATAIRE (E15). */
class OfferStatsService
{
    /**
     * @return array<string, mixed>
     */
    public function pour(User $prestataire, ?Carbon $depuis = null, ?Carbon $jusqua = null): array
    {
        $depuis ??= Carbon::now()->subDays(30);
        $jusqua ??= Carbon::now();

        $lignes = MissionAssignment::query()
            ->where('user_id', $prestataire->id)
            ->whereBetween('created_at', [$depuis, $jusqua])
            // `status` A ÉTÉ RETIRÉE DE CETTE LISTE PARCE QUE LA COLONNE N'EXISTE PLUS.
            ->get(['id', 'assignment_status', 'accepted_at', 'declined_at', 'response_seconds', 'decline_reason', 'expires_at', 'created_at']);

        $total = $lignes->count();
        $acceptees = $lignes->whereNotNull('accepted_at')->count();
        $refusees = $lignes->whereNotNull('declined_at')->count();

        // SANS RÉPONSE : ni acceptée, ni refusée, et l'échéance est passée.
        $sansReponse = $lignes
            ->filter(fn (MissionAssignment $ligne) => $ligne->accepted_at === null
                && $ligne->declined_at === null
                && $ligne->expires_at !== null
                && $ligne->expires_at->isPast())
            ->count();

        return [
            'from' => $depuis->toDateString(),
            'to' => $jusqua->toDateString(),
            'offers_count' => $total,
            'accepted_count' => $acceptees,
            'declined_count' => $refusees,
            // Une expiration se corrige en répondant plus vite, un refus en changeant ce qu'on
            // accepte : les mélanger donnerait un conseil faux.
            'expired_count' => $sansReponse,
            'acceptance_rate' => $total > 0 ? round($acceptees / $total * 100, 1) : null,
            // La MÉDIANE, pas la moyenne : une offre répondue quarante minutes plus tard depuis un
            // tunnel décalerait une moyenne au point de la rendre absurde.
            'median_response_seconds' => $this->medianeDeReponse($lignes),
            'decline_reasons' => $this->motifsDeRefus($lignes),
        ];
    }

    /**
     * @param  Collection<int, MissionAssignment>  $lignes
     */
    protected function medianeDeReponse(Collection $lignes): ?int
    {
        $temps = $lignes
            ->pluck('response_seconds')
            ->filter(fn ($valeur) => is_numeric($valeur) && (int) $valeur >= 0)
            ->map(fn ($valeur) => (int) $valeur)
            ->sort()
            ->values();

        if ($temps->isEmpty()) {
            return null;
        }

        $milieu = intdiv($temps->count(), 2);

        return $temps->count() % 2 === 1
            ? $temps[$milieu]
            : (int) round(($temps[$milieu - 1] + $temps[$milieu]) / 2);
    }

    /**
     * Les motifs de refus, du plus fréquent au moins. C'EST CE QUI SE CORRIGE.
     *
     * @param  Collection<int, MissionAssignment>  $lignes
     * @return list<array<string, mixed>>
     */
    protected function motifsDeRefus(Collection $lignes): array
    {
        return $lignes
            ->whereNotNull('declined_at')
            ->groupBy(fn (MissionAssignment $ligne) => $ligne->decline_reason ?: 'Sans motif')
            ->map(fn ($groupe, $motif) => ['reason' => $motif, 'count' => $groupe->count()])
            ->sortByDesc('count')
            ->values()
            ->all();
    }
}

<?php

namespace App\Services\Provider;

use App\Models\MissionAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * LES STATISTIQUES D'OFFRES D'UN PRESTATAIRE (E15).
 *
 * TOUT EST DÉJÀ DANS `mission_assignments`, et personne ne le lit. La table porte le temps de
 * réponse, les refus avec leur motif, les expirations — c'est-à-dire la réponse exacte à la question
 * que tout indépendant se pose : « pourquoi est-ce que je reçois moins de courses qu'avant ? »
 *
 * TROIS RÉPONSES POSSIBLES, ET IL FAUT LES DISTINGUER. Trop lent à répondre : l'offre part au
 * suivant. Trop de refus : le moteur de matching en tient compte. Ou simplement moins d'offres
 * reçues, ce qui n'est pas la faute du prestataire et ne doit surtout pas lui être présenté comme
 * telle. Les confondre ferait culpabiliser quelqu'un pour une baisse de demande dans sa zone.
 *
 * LE TEMPS DE RÉPONSE EST UNE MÉDIANE, PAS UNE MOYENNE. Une seule offre reçue pendant un tunnel —
 * répondue quarante minutes plus tard — décalerait une moyenne au point de la rendre absurde. La
 * médiane décrit le comportement ordinaire, qui est ce qu'on cherche à améliorer.
 *
 * LES OFFRES SANS RÉPONSE NE SONT PAS DES REFUS. Une expiration se corrige en répondant plus vite,
 * un refus se corrige en changeant ce qu'on accepte : les mélanger donnerait un conseil faux.
 */
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
            /*
             * `status` A ÉTÉ RETIRÉE DE CETTE LISTE PARCE QUE LA COLONNE N'EXISTE PLUS.
             *
             * C'était une colonne dormante — NOT NULL, défaut « assigned », jamais écrite par une
             * ligne de code — supprimée avec `role` le 2026-09-01. La retirer de la table sans la
             * retirer d'ici laissait une sélection explicite sur un identifiant inconnu : MySQL
             * refuse la requête, et cet écran est celui des statistiques d'offres du prestataire.
             *
             * Rien ne se perd : aucun calcul de ce fichier ne lisait `status`. Ils s'appuient tous
             * sur `accepted_at` et `declined_at`, et son propre commentaire dit pourquoi —
             * « plusieurs vocabulaires de statut coexistent dans cette table, et aucun ne dit
             * "le temps a passé" ».
             */
            ->get(['id', 'assignment_status', 'accepted_at', 'declined_at', 'response_seconds', 'decline_reason', 'expires_at', 'created_at']);

        $total = $lignes->count();
        $acceptees = $lignes->whereNotNull('accepted_at')->count();
        $refusees = $lignes->whereNotNull('declined_at')->count();

        /*
         * SANS RÉPONSE : ni acceptée, ni refusée, et l'échéance est passée. C'est le cas qu'on ne
         * peut pas déduire d'un statut — plusieurs vocabulaires de statut coexistent dans cette
         * table, et aucun ne dit « le temps a passé ».
         */
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
     * Les motifs de refus, du plus fréquent au moins.
     *
     * C'EST CE QUI SE CORRIGE. « Trop loin » se règle en resserrant sa zone, « déjà pris » en
     * ajustant ses disponibilités : un taux de refus sans ses motifs ne dit pas quoi faire.
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

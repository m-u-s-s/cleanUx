<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\BookingMatchingDecision;

/**
 * Les décisions de matching et leur explication.
 *
 * Cette table EST la trace d’explicabilité : elle conserve les poids appliqués et le détail des
 * candidats au moment de la décision. La modifier reviendrait à réécrire l’explication après
 * coup — ce qui la vide de sa valeur.
 *
 * @extends EloquentResource<BookingMatchingDecision>
 */
class MatchingDecisionResource extends EloquentResource
{
    public function key(): string
    {
        return 'matching';
    }

    protected function model(): string
    {
        return BookingMatchingDecision::class;
    }

    protected function columnSpec(): array
    {
        return [
            'strategy' => ['Stratégie', Column::TYPE_BADGE],
            'candidates_count' => ['Candidats', Column::TYPE_NUMBER],
            'selected_score' => ['Score retenu', Column::TYPE_NUMBER],
            'top_score' => ['Meilleur score', Column::TYPE_NUMBER],
            'created_at' => ['Décidée le', Column::TYPE_DATETIME],
        ];
    }

    protected function searchable(): array
    {
        return ['strategy', 'algorithm_version'];
    }

    protected function searchLabel(): string
    {
        return 'Stratégie ou version';
    }

    protected function detailSpec(): array
    {
        return [
            'algorithm_version' => 'Version de l’algorithme',
            'runner_up_score' => 'Second score',
        ];
    }
}

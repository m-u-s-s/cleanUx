<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Admin\Console\Field;
use App\Models\Booking;
use App\Models\BookingMatchingDecision;
use App\Services\Booking\SmartDispatchService;

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

    public function globalActions(): array
    {
        return [
            /*
             * SIMULER n'écrit rien : le geste explique pourquoi tel prestataire a été retenu, ou
             * pourquoi aucun ne l'a été. C'est une action plutôt qu'une colonne parce qu'elle
             * demande un paramètre — la mission à expliquer — et qu'aucune liste ne peut le
             * deviner.
             */
            Action::make('simulate', 'Simuler le matching', function (array $valeurs) {
                $rdv = Booking::find((int) $valeurs['booking_id']);

                if (! $rdv) {
                    return ['ok' => false, 'message' => 'Mission introuvable.'];
                }

                return ['ok' => true, 'scores' => app(SmartDispatchService::class)->explainScores($rdv)];
            })->requires([
                Field::make('booking_id', 'Identifiant de la mission', Field::TYPE_NUMBER)
                    ->rules(['required', 'integer', 'min:1']),
            ]),
        ];
    }
}

<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Admin\Console\Field;
use App\Models\RiskHold;
use App\Services\Risk\RiskScoringEngine;

/**
 * Les blocages de risque en attente de revue.
 *
 * POURQUOI CE DESCRIPTEUR EXISTE À CÔTÉ DE CELUI DES ÉVALUATIONS. La page web du risque montre deux
 * choses : les ÉVALUATIONS, qui expliquent un score, et les BLOCAGES, qui empêchent une action et
 * attendent une décision humaine. Un descripteur ne sert qu'un modèle ; celui des évaluations ne
 * pouvait donc offrir ni approbation ni rejet, et le geste principal de la page manquait au mobile.
 *
 * LA DÉCISION PASSE PAR LE MOTEUR DE SCORE, jamais par une écriture directe : lever un blocage
 * débloque l'utilisateur, journalise la revue et met à jour l'évaluation liée. Écrire `status` à la
 * main laisserait l'utilisateur bloqué avec un blocage marqué résolu — l'inverse exact de ce qu'on
 * croit avoir fait.
 *
 * @extends EloquentResource<RiskHold>
 */
class RiskHoldResource extends EloquentResource
{
    public function key(): string
    {
        return 'risk-holds';
    }

    protected function model(): string
    {
        return RiskHold::class;
    }

    protected function columnSpec(): array
    {
        return [
            'status' => ['Statut', Column::TYPE_BADGE],
            'reason' => ['Motif'],
            'subject_type' => ['Objet'],
            'expires_at' => ['Expire le', Column::TYPE_DATE],
            'created_at' => ['Créé le', Column::TYPE_DATE],
        ];
    }

    protected function searchable(): array
    {
        return ['reason'];
    }

    protected function searchLabel(): string
    {
        return 'Motif du blocage';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'pending', 'label' => 'En attente'],
                ['value' => 'approved', 'label' => 'Approuvé'],
                ['value' => 'rejected', 'label' => 'Rejeté'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'review_notes' => 'Notes de revue',
            'reviewed_at' => 'Revu le',
        ];
    }

    public function actions(): array
    {
        return [
            /*
             * Approuver LÈVE le blocage : l'utilisateur retrouve l'action qui lui était refusée.
             * Rejeter la confirme. Les deux passent par le moteur, qui journalise la revue et
             * répercute sur l'évaluation liée.
             */
            Action::make('approve', 'Approuver — lever le blocage', function (RiskHold $hold, array $valeurs) {
                app(RiskScoringEngine::class)->reviewHold(
                    $hold,
                    request()->user(),
                    'approved',
                    (string) ($valeurs['notes'] ?? 'Approuvé depuis le mobile'),
                );

                return ['ok' => true];
            })->requires([
                Field::make('notes', 'Notes', Field::TYPE_TEXTAREA)->rules(['nullable', 'string', 'max:1000']),
            ]),

            Action::make('reject', 'Rejeter — maintenir le blocage', function (RiskHold $hold, array $valeurs) {
                app(RiskScoringEngine::class)->reviewHold(
                    $hold,
                    request()->user(),
                    'rejected',
                    (string) $valeurs['notes'],
                );

                return ['ok' => true];
            })->requires([
                // Maintenir un blocage prive quelqu'un d'une action : la raison doit être écrite,
                // c'est elle qu'on relira si la décision est contestée.
                Field::make('notes', 'Motif du maintien', Field::TYPE_TEXTAREA)
                    ->rules(['required', 'string', 'min:5', 'max:1000']),
            ])->destructive('Le blocage sera maintenu et l’action restera refusée.'),
        ];
    }
}

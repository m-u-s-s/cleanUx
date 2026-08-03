<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\OnboardingProgress;

/**
 * L’avancement des parcours d’inscription.
 *
 * LECTURE SEULE. Une étape se valide en la RÉUSSISSANT : chaque étape a son validateur, qui
 * vérifie des conditions réparties dans plusieurs modules. Marquer une étape faite à la main
 * ferait entrer dans l’application quelqu’un dont le dossier ne tient pas.
 *
 * @extends EloquentResource<OnboardingProgress>
 */
class OnboardingProgressResource extends EloquentResource
{
    public function key(): string
    {
        return 'onboarding-v2';
    }

    protected function model(): string
    {
        return OnboardingProgress::class;
    }

    protected function columnSpec(): array
    {
        return [
            'status' => ['Statut', Column::TYPE_BADGE],
            'current_step_code' => ['Étape en cours'],
            'percent_complete' => ['Avancement', Column::TYPE_NUMBER],
            'started_at' => ['Démarré le', Column::TYPE_DATETIME],
            'completed_at' => ['Terminé le', Column::TYPE_DATETIME],
        ];
    }

    protected function searchable(): array
    {
        return ['current_step_code'];
    }

    protected function searchLabel(): string
    {
        return 'Étape';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'in_progress', 'label' => 'En cours'],
                ['value' => 'completed', 'label' => 'Terminé'],
                ['value' => 'abandoned', 'label' => 'Abandonné'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'abandoned_at' => 'Abandonné le',
        ];
    }
}

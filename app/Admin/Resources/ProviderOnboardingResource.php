<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\ProviderProfile;
use App\Support\ActivityLogger;

/**
 * L’avancement du parcours d’inscription des prestataires.
 *
 * MÊME TABLE QUE « Inscriptions », AUTRE QUESTION : ici on regarde OÙ EN EST le dossier, pas
 * s’il faut l’approuver. Les deux listes existent parce qu’on ne les ouvre pas au même moment,
 * et une seule obligerait à filtrer à chaque fois pour retrouver la sienne.
 *
 * @extends EloquentResource<ProviderProfile>
 */
class ProviderOnboardingResource extends EloquentResource
{
    public function key(): string
    {
        return 'onboarding-providers';
    }

    protected function model(): string
    {
        return ProviderProfile::class;
    }

    protected function columnSpec(): array
    {
        return [
            'onboarding_step' => ['Étape'],
            'status' => ['Statut', Column::TYPE_BADGE],
            'onboarding_started_at' => ['Démarré le', Column::TYPE_DATETIME],
            'onboarding_completed_at' => ['Terminé le', Column::TYPE_DATETIME],
            'kyc_completed_at' => ['KYC terminé le', Column::TYPE_DATETIME],
        ];
    }

    protected function searchable(): array
    {
        return ['onboarding_step'];
    }

    protected function searchLabel(): string
    {
        return 'Étape';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'pending', 'label' => 'En attente'],
                ['value' => 'active', 'label' => 'Actif'],
                ['value' => 'suspended', 'label' => 'Suspendu'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'kyc_provider' => 'Fournisseur KYC',
            'kyc_score' => 'Score KYC',
            'provider_type' => 'Type',
        ];
    }

    public function actions(): array
    {
        return [
            /*
             * Le web approuve un onboarding après avoir lu les pièces et le dossier. Ici on sert
             * la RELANCE, qui est le geste de terrain : rappeler au prestataire ce qui manque.
             * L'approbation elle-même reste sur le web, où l'écran montre ce qu'on approuve.
             */
            Action::make('remind', 'Relancer le prestataire', function (ProviderProfile $profile) {
                ActivityLogger::log('provider_onboarding.reminded', $profile, [
                    'by' => request()->user()?->id,
                ]);

                return ['ok' => true];
            }),
        ];
    }
}

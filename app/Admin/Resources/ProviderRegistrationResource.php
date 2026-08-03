<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\ProviderProfile;

/**
 * Les inscriptions de prestataires à instruire.
 *
 * L’APPROBATION passe par le module d’onboarding : elle débloque le parcours, déclenche les
 * vérifications restantes et prévient l’intéressé. Poser `verification_status = ’verified’` ici
 * ferait un prestataire vérifié que rien n’a vérifié — et il recevrait des missions.
 *
 * @extends EloquentResource<ProviderProfile>
 */
class ProviderRegistrationResource extends EloquentResource
{
    public function key(): string
    {
        return 'provider-registrations';
    }

    protected function model(): string
    {
        return ProviderProfile::class;
    }

    protected function columnSpec(): array
    {
        return [
            'provider_type' => ['Type', Column::TYPE_BADGE],
            'status' => ['Statut', Column::TYPE_BADGE],
            'verification_status' => ['Vérification', Column::TYPE_BADGE],
            'self_registered_at' => ['Inscrit le', Column::TYPE_DATETIME],
            'onboarding_step' => ['Étape'],
        ];
    }

    protected function searchable(): array
    {
        return ['bio', 'verification_notes'];
    }

    protected function searchLabel(): string
    {
        return 'Bio ou notes de vérification';
    }

    protected function selectFilters(): array
    {
        return [
            'verification_status' => ['Vérification', 'verification_status', [
                ['value' => 'unverified', 'label' => 'Non vérifié'],
                ['value' => 'pending', 'label' => 'En attente'],
                ['value' => 'verified', 'label' => 'Vérifié'],
                ['value' => 'rejected', 'label' => 'Refusé'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'verification_notes' => 'Notes de vérification',
            'onboarding_completed_at' => 'Parcours terminé le',
            'verified_at' => 'Vérifié le',
        ];
    }
}

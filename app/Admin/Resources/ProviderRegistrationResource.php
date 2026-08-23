<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Admin\Console\Field;
use App\Models\ProviderProfile;
use App\Support\ActivityLogger;

/**
 * Les inscriptions de prestataires à instruire.
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

    public function actions(): array
    {
        return [
            // Refuser une inscription.
            Action::make('reject', 'Refuser l’inscription', function (ProviderProfile $profile, array $valeurs) {
                $profile->forceFill([
                    'status' => 'rejected',
                    'verification_status' => 'rejected',
                ])->save();

                ActivityLogger::log('provider_registration.rejected', $profile, [
                    'reason' => $valeurs['reason'],
                ]);

                return ['ok' => true];
            })->requires([
                Field::make('reason', 'Motif du refus', Field::TYPE_TEXTAREA)
                    ->rules(['required', 'string', 'min:5', 'max:500']),
            ])->destructive('L’inscription sera refusée.'),
        ];
    }
}

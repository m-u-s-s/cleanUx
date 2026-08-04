<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\User;

/**
 * Les clients premium et leur abonnement.
 *
 * L’OCTROI du premium passe par le module d’abonnement : il crée le cycle de facturation qui va
 * avec. Écrire `plan_type = ’premium’` ici donnerait l’avantage sans jamais le facturer.
 *
 * @extends EloquentResource<User>
 */
class PremiumClientResource extends EloquentResource
{
    public function key(): string
    {
        return 'premium';
    }

    protected function model(): string
    {
        return User::class;
    }

    protected function columnSpec(): array
    {
        return [
            'name' => ['Client'],
            'plan_type' => ['Formule', Column::TYPE_BADGE],
            'plan_status' => ['Statut', Column::TYPE_BADGE],
            'premium_started_at' => ['Premium depuis', Column::TYPE_DATE],
            'premium_renewal_at' => ['Renouvellement', Column::TYPE_DATE],
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'email'];
    }

    protected function searchLabel(): string
    {
        return 'Nom ou email';
    }

    protected function selectFilters(): array
    {
        return [
            'plan_status' => ['Statut', 'plan_status', [
                ['value' => 'active', 'label' => 'Actif'],
                ['value' => 'inactive', 'label' => 'Inactif'],
                ['value' => 'cancelled', 'label' => 'Annulé'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'email' => 'Email',
            'trial_ends_at' => 'Fin d’essai',
        ];
    }

    public function actions(): array
    {
        return [
            /*
             * Les quatre gestes du web, à l'identique. Ce sont de simples écritures d'état — aucun
             * service ne les porte côté web non plus, et en inventer un ici créerait une règle qui
             * n'existe nulle part ailleurs.
             */
            Action::make('set-premium', 'Passer en Premium', function (User $client) {
                $client->forceFill([
                    'plan_type' => 'premium',
                    'plan_status' => 'active',
                    'premium_started_at' => now(),
                    'premium_renewal_at' => now()->addMonth(),
                ])->save();

                return ['plan_type' => 'premium'];
            }),

            Action::make('set-standard', 'Repasser en Standard', function (User $client) {
                $client->forceFill(['plan_type' => 'standard', 'plan_status' => 'inactive'])->save();

                return ['plan_type' => 'standard'];
            })->destructive('Le client repassera en offre Standard.'),

            Action::make('suspend-plan', 'Suspendre le plan', function (User $client) {
                $client->forceFill(['plan_status' => 'past_due'])->save();

                return ['plan_status' => 'past_due'];
            }),

            Action::make('reactivate-plan', 'Réactiver le plan', function (User $client) {
                $client->forceFill([
                    'plan_type' => 'premium',
                    'plan_status' => 'active',
                    'premium_renewal_at' => now()->addMonth(),
                ])->save();

                return ['plan_status' => 'active'];
            }),
        ];
    }
}

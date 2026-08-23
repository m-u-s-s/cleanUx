<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\ProviderProfile;

/**
 * L’état des comptes Stripe Connect des prestataires.
 *
 * @extends EloquentResource<ProviderProfile>
 */
class StripeConnectResource extends EloquentResource
{
    public function key(): string
    {
        return 'stripe-connect';
    }

    protected function model(): string
    {
        return ProviderProfile::class;
    }

    protected function columnSpec(): array
    {
        return [
            'stripe_connect_status' => ['Statut Connect', Column::TYPE_BADGE],
            'stripe_connect_account_id' => ['Compte Stripe'],
            'stripe_connect_onboarded_at' => ['Activé le', Column::TYPE_DATETIME],
            'commission_rate' => ['Commission', Column::TYPE_NUMBER],
            'status' => ['Statut prestataire', Column::TYPE_BADGE],
        ];
    }

    protected function searchable(): array
    {
        return ['stripe_connect_account_id'];
    }

    protected function searchLabel(): string
    {
        return 'Identifiant de compte Stripe';
    }

    protected function selectFilters(): array
    {
        return [
            'stripe_connect_status' => ['Statut Connect', 'stripe_connect_status', [
                ['value' => 'pending', 'label' => 'En attente'],
                ['value' => 'onboarded', 'label' => 'Activé'],
                ['value' => 'restricted', 'label' => 'Restreint'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'hourly_rate' => 'Taux horaire',
            'provider_type' => 'Type',
        ];
    }
}

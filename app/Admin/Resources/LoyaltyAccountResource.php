<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\LoyaltyAccount;

/**
 * Les comptes de fidélité.
 *
 * Les points ne se corrigent pas ici. Le registre de fidélité est IMMUABLE par construction :
 * chaque mouvement y est une ligne, et écrire un solde à la main le désaccorderait de son
 * historique — un solde que plus rien n’explique.
 *
 * @extends EloquentResource<LoyaltyAccount>
 */
class LoyaltyAccountResource extends EloquentResource
{
    public function key(): string
    {
        return 'loyalty';
    }

    protected function model(): string
    {
        return LoyaltyAccount::class;
    }

    protected function columnSpec(): array
    {
        return [
            'lifetime_points' => ['Points cumulés', Column::TYPE_NUMBER],
            'period_points' => ['Points période', Column::TYPE_NUMBER],
            'redeemable_points' => ['Points utilisables', Column::TYPE_NUMBER],
            'tier_started_at' => ['Palier depuis', Column::TYPE_DATE],
            'last_activity_at' => ['Dernière activité', Column::TYPE_DATETIME],
        ];
    }

    protected function searchable(): array
    {
        return [];
    }

    protected function searchLabel(): string
    {
        return 'Rechercher';
    }

    protected function detailSpec(): array
    {
        return [
            'tier_evaluated_at' => 'Palier évalué le',
            'points_period_started_at' => 'Période depuis',
        ];
    }
}

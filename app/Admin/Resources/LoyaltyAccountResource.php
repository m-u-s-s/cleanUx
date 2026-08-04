<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Admin\Console\Field;
use App\Models\LoyaltyAccount;
use App\Services\Loyalty\LoyaltyService;

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

    public function actions(): array
    {
        return [
            /*
             * Ajuster des points À LA MAIN. Le motif est obligatoire et long d'au moins cinq
             * caractères, comme sur le web : le ledger de fidélité est immuable, chaque écriture y
             * reste, et une ligne sans raison devient incompréhensible six mois plus tard.
             *
             * Zéro est refusé : un ajustement nul n'est pas une intention, c'est un formulaire
             * envoyé par erreur.
             */
            Action::make('adjust', 'Ajuster les points', function (LoyaltyAccount $compte, array $valeurs) {
                app(LoyaltyService::class)->adminAdjust(
                    $compte->user,
                    (int) $valeurs['points'],
                    request()->user(),
                    (string) $valeurs['reason'],
                );

                return ['ok' => true];
            })->requires([
                Field::make('points', 'Points (négatif pour retirer)', Field::TYPE_NUMBER)
                    ->rules(['required', 'integer', 'min:-100000', 'max:100000', 'not_in:0']),
                Field::make('reason', 'Motif', Field::TYPE_TEXTAREA)
                    ->rules(['required', 'string', 'min:5', 'max:500']),
            ]),
        ];
    }
}

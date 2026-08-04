<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\PromoCampaign;
use App\Support\ActivityLogger;

/**
 * Les campagnes promotionnelles et leur enveloppe.
 *
 * Le budget consommé est affiché à côté du plafond : une campagne promotionnelle se surveille
 * là où on voit ce qu’elle coûte, pas sur une page séparée qu’on ouvre après coup.
 *
 * @extends EloquentResource<PromoCampaign>
 */
class PromoCampaignResource extends EloquentResource
{
    public function key(): string
    {
        return 'promo-campaigns';
    }

    protected function model(): string
    {
        return PromoCampaign::class;
    }

    protected function columnSpec(): array
    {
        return [
            'name' => ['Campagne'],
            'status' => ['Statut', Column::TYPE_BADGE],
            'budget_cap' => ['Plafond', Column::TYPE_MONEY],
            'total_discounted' => ['Consommé', Column::TYPE_MONEY],
            'total_redemptions' => ['Utilisations', Column::TYPE_NUMBER],
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'slug', 'description'];
    }

    protected function searchLabel(): string
    {
        return 'Nom ou description';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'draft', 'label' => 'Brouillon'],
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'paused', 'label' => 'Suspendue'],
                ['value' => 'ended', 'label' => 'Terminée'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'starts_at' => 'Débute le',
            'ends_at' => 'Se termine le',
            'target_audience' => 'Audience',
        ];
    }

    public function actions(): array
    {
        return [
            Action::make('pause', 'Mettre en pause', function (PromoCampaign $campagne) {
                $campagne->forceFill(['status' => PromoCampaign::STATUS_PAUSED])->save();
                ActivityLogger::log('promo_campaign.paused', $campagne, ['admin_user_id' => request()->user()?->id]);

                return ['status' => 'paused'];
            }),

            Action::make('activate', 'Activer', function (PromoCampaign $campagne) {
                $campagne->forceFill(['status' => PromoCampaign::STATUS_ACTIVE])->save();
                ActivityLogger::log('promo_campaign.activated', $campagne, ['admin_user_id' => request()->user()?->id]);

                return ['status' => 'active'];
            }),
        ];
    }
}

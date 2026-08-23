<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\MarketingCampaign;
use App\Services\Marketing\CampaignEngine;

/**
 * Les campagnes marketing. PAS DE LANCEMENT NI DE PAUSE ICI.
 *
 * @extends EloquentResource<MarketingCampaign>
 */
class MarketingCampaignResource extends EloquentResource
{
    public function key(): string
    {
        return 'marketing';
    }

    protected function model(): string
    {
        return MarketingCampaign::class;
    }

    protected function columnSpec(): array
    {
        return [
            'name' => ['Campagne'],
            'type' => ['Type', Column::TYPE_BADGE],
            'status' => ['Statut', Column::TYPE_BADGE],
            'scheduled_at' => ['Programmée le', Column::TYPE_DATETIME],
            'created_at' => ['Créée le', Column::TYPE_DATE],
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'code', 'description'];
    }

    protected function searchLabel(): string
    {
        return 'Nom, code ou description';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'draft', 'label' => 'Brouillon'],
                ['value' => 'scheduled', 'label' => 'Programmée'],
                ['value' => 'running', 'label' => 'En cours'],
                ['value' => 'paused', 'label' => 'En pause'],
                ['value' => 'completed', 'label' => 'Terminée'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'locale' => 'Langue',
            'opt_in_required' => 'Opt-in requis',
            'started_at' => 'Démarrée le',
            'ended_at' => 'Terminée le',
        ];
    }

    public function actions(): array
    {
        return [
            // Planifier une campagne CONSTITUE sa liste de destinataires.
            Action::make('schedule', 'Planifier la campagne', function (MarketingCampaign $campagne) {
                $destinataires = app(CampaignEngine::class)->schedule($campagne);

                return ['recipients' => $destinataires];
            }),

            Action::make('pause', 'Mettre en pause', function (MarketingCampaign $campagne) {
                app(CampaignEngine::class)->pause($campagne);

                return ['ok' => true];
            }),

            Action::make('cancel', 'Annuler la campagne', function (MarketingCampaign $campagne) {
                app(CampaignEngine::class)->cancel($campagne);

                return ['ok' => true];
            })->destructive('La campagne sera annulée et ses envois restants abandonnés.'),
        ];
    }
}

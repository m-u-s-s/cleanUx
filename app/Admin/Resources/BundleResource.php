<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\MultiTradeBundle;

/**
 * Les regroupements multi-métiers.
 *
 * Le DEVIS d’un regroupement est encore saisi à la main par l’administration : c’est un manque
 * connu de la plateforme, pas une limite de cette console. Cette liste sert à les retrouver et
 * à suivre leur état.
 *
 * @extends EloquentResource<MultiTradeBundle>
 */
class BundleResource extends EloquentResource
{
    public function key(): string
    {
        return 'bundles';
    }

    protected function model(): string
    {
        return MultiTradeBundle::class;
    }

    protected function columnSpec(): array
    {
        return [
            'code' => ['Référence'],
            'status' => ['Statut', Column::TYPE_BADGE],
            'total_estimated_cents' => ['Estimé (cents)', Column::TYPE_NUMBER],
            'total_quoted_cents' => ['Devis (cents)', Column::TYPE_NUMBER],
            'created_at' => ['Créé le', Column::TYPE_DATE],
        ];
    }

    protected function searchable(): array
    {
        return ['code', 'name', 'address'];
    }

    protected function searchLabel(): string
    {
        return 'Référence, nom ou adresse';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'draft', 'label' => 'Brouillon'],
                ['value' => 'quoting', 'label' => 'En cotation'],
                ['value' => 'accepted', 'label' => 'Accepté'],
                ['value' => 'completed', 'label' => 'Terminé'],
                ['value' => 'cancelled', 'label' => 'Annulé'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'bundle_discount_percent' => 'Remise (%)',
            'preferred_start_date' => 'Début souhaité',
            'accepted_at' => 'Accepté le',
        ];
    }
}

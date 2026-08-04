<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Admin\Console\Field;
use App\Models\CancellationPolicy;

/**
 * Les politiques d’annulation, versionnées.
 *
 * Une politique est VERSIONNÉE parce que les annulations passées ont été calculées avec la
 * version en vigueur ce jour-là. L’éditer sur place réécrirait rétroactivement des frais déjà
 * facturés : on en publie une nouvelle version, on ne corrige pas l’ancienne.
 *
 * @extends EloquentResource<CancellationPolicy>
 */
class CancellationPolicyResource extends EloquentResource
{
    public function key(): string
    {
        return 'cancellations';
    }

    protected function model(): string
    {
        return CancellationPolicy::class;
    }

    protected function columnSpec(): array
    {
        return [
            'code' => ['Code'],
            'name' => ['Politique'],
            'actor_role' => ['Rôle', Column::TYPE_BADGE],
            'version' => ['Version', Column::TYPE_NUMBER],
            'is_active' => ['Active', Column::TYPE_BOOL],
        ];
    }

    protected function searchable(): array
    {
        return ['code', 'name', 'description'];
    }

    protected function searchLabel(): string
    {
        return 'Code, nom ou description';
    }

    protected function selectFilters(): array
    {
        return [
            'actor_role' => ['Rôle', 'actor_role', [
                ['value' => 'client', 'label' => 'Client'],
                ['value' => 'provider', 'label' => 'Prestataire'],
                ['value' => 'admin', 'label' => 'Administration'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'description' => 'Description',
            'valid_from' => 'Valide à partir du',
            'valid_until' => 'Valide jusqu’au',
        ];
    }

    public function formFields(): array
    {
        return [
            Field::make('name', 'Nom de la politique')->rules(['required', 'string', 'max:255']),
            Field::make('code', 'Code')->rules(['required', 'string', 'max:60']),
            // Une politique s'applique à un RÔLE : la même annulation ne coûte pas la même
            // chose au client et au prestataire.
            Field::select('actor_role', 'S’applique à', [
                ['value' => 'client', 'label' => 'Client'],
                ['value' => 'provider', 'label' => 'Prestataire'],
                ['value' => 'admin', 'label' => 'Administrateur'],
            ])->rules(['required', 'string', 'max:16']),
            Field::make('description', 'Description', Field::TYPE_TEXTAREA)
                ->rules(['nullable', 'string', 'max:2000']),
        ];
    }
}

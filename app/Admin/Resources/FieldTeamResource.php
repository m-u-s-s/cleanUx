<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Admin\Console\Field;
use App\Models\FieldTeam;

/**
 * Les équipes de terrain et les partenaires.
 *
 * La COMPOSITION d’une équipe vit dans sa propre table : ajouter un membre engage des
 * vérifications de certification et de zone que le rendu générique ne saurait pas conduire.
 *
 * @extends EloquentResource<FieldTeam>
 */
class FieldTeamResource extends EloquentResource
{
    public function key(): string
    {
        return 'teams';
    }

    protected function model(): string
    {
        return FieldTeam::class;
    }

    protected function columnSpec(): array
    {
        return [
            'name' => ['Équipe'],
            'status' => ['Statut', Column::TYPE_BADGE],
            'is_internal' => ['Interne', Column::TYPE_BOOL],
            'max_concurrent_missions' => ['Missions simultanées max', Column::TYPE_NUMBER],
            'created_at' => ['Créée le', Column::TYPE_DATE],
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'slug', 'notes'];
    }

    protected function searchLabel(): string
    {
        return 'Nom ou notes';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'Inactive'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'notes' => 'Notes',
        ];
    }

    public function formFields(): array
    {
        return [
            Field::make('name', 'Nom de l’équipe')->rules(['required', 'string', 'max:255']),
            Field::select('status', 'Statut', [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'Inactive'],
            ])->rules(['nullable', 'in:active,inactive']),
        ];
    }

    public function actions(): array
    {
        return [
            Action::make('toggle-active', 'Activer / désactiver', function (FieldTeam $equipe) {
                // La table porte un `status`, pas un booléen : basculer un `is_active` inexistant
                // aurait produit une action silencieusement inerte.
                $equipe->forceFill([
                    'status' => $equipe->status === 'active' ? 'inactive' : 'active',
                ])->save();

                return ['status' => $equipe->fresh()->status];
            }),
        ];
    }
}

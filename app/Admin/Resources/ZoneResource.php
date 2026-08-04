<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\Country;
use App\Models\ServiceZone;

/**
 * Les zones de service.
 *
 * Le périmètre postal et les règles de couverture ne s'éditent pas ici : ils engagent le
 * matching et la tarification, et se modifient depuis la page web qui montre leurs conséquences.
 *
 * @extends EloquentResource<ServiceZone>
 */
class ZoneResource extends EloquentResource
{
    public function key(): string
    {
        return 'zones';
    }

    protected function model(): string
    {
        return ServiceZone::class;
    }

    protected function columnSpec(): array
    {
        return [
            'name' => ['Zone'],
            'code' => ['Code'],
            'status' => ['Statut', Column::TYPE_BADGE],
            'is_bookable' => ['Réservable', Column::TYPE_BOOL],
            'priority' => ['Priorité', Column::TYPE_NUMBER],
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'code', 'slug'];
    }

    protected function searchLabel(): string
    {
        return 'Nom ou code';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'Inactive'],
            ]],

            /*
             * Le cloisonnement par pays, servi au mobile.
             *
             * Il DOIT vivre ici plutôt que côté client : un filtre appliqué à l'affichage laisse
             * passer les actions, et l'écran des zones belges montrerait Paris dès qu'un second
             * marché ouvrirait. Les options sont calculées, faute de quoi il faudrait rééditer ce
             * fichier à chaque pays ajouté.
             */
            'country_id' => ['Pays', 'country_id', Country::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Country $pays) => ['value' => (string) $pays->id, 'label' => (string) $pays->name])
                ->all()],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'coverage_type' => 'Type de couverture',
            'minimum_notice_hours' => 'Préavis minimal (h)',
            'maximum_daily_jobs' => 'Missions max/jour',
            'notes' => 'Notes',
        ];
    }
}

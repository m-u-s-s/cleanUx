<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Admin\Console\Field;
use App\Models\Country;
use App\Services\Catalog\GeoGuard;
use Illuminate\Database\Eloquent\Model;

/**
 * Les pays ouverts à l’exploitation.
 *
 * `booking_enabled` coupe la prise de commande dans tout un pays : la bascule est annoncee comme
 * destructive parce qu'elle l’est — plus aucun client de ce pays ne peut commander.
 *
 * @extends EloquentResource<Country>
 */
class CountryResource extends EloquentResource
{
    public function key(): string
    {
        return 'countries';
    }

    protected function model(): string
    {
        return Country::class;
    }

    protected function columnSpec(): array
    {
        return [
            'iso_code' => ['Code ISO'],
            'name' => ['Pays'],
            'currency_code' => ['Devise', Column::TYPE_BADGE],
            'is_active' => ['Actif', Column::TYPE_BOOL],
            'booking_enabled' => ['Commande ouverte', Column::TYPE_BOOL],
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'iso_code', 'official_name'];
    }

    protected function searchLabel(): string
    {
        return 'Nom ou code ISO';
    }

    protected function selectFilters(): array
    {
        return [
            'market_stage' => ['Stade', 'market_stage', [
                ['value' => 'pilot', 'label' => 'Pilote'],
                ['value' => 'live', 'label' => 'Ouvert'],
                ['value' => 'paused', 'label' => 'Suspendu'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'default_locale' => 'Langue par défaut',
            'phone_code' => 'Indicatif',
            'timezone' => 'Fuseau',
        ];
    }

    /**
     * Les champs d'un pays.
     *
     * `booking_enabled` et `market_stage` restent hors du formulaire : ils engagent l'ouverture
     * commerciale d'un marché, qui se décide sur le web où l'écran en montre les conséquences.
     */
    public function formFields(): array
    {
        return [
            Field::make('iso_code', 'Code ISO (2 lettres)')->rules(['required', 'string', 'size:2']),
            Field::make('name', 'Nom')->rules(['required', 'string', 'max:120']),
            Field::make('currency_code', 'Devise')->rules(['required', 'string', 'size:3']),
            Field::make('default_locale', 'Langue par défaut')->rules(['nullable', 'string', 'max:10']),
            Field::make('timezone', 'Fuseau horaire')->rules(['nullable', 'string', 'max:64']),
            Field::make('phone_code', 'Indicatif téléphonique')->rules(['nullable', 'string', 'max:8']),
        ];
    }

    public function actions(): array
    {
        return [
            /*
             * Basculer l'activation ne touche QUE le pays.
             *
             * Propager l'extinction aux zones ferait perdre celles qui étaient déjà fermées pour
             * leur propre raison : la réactivation les rallumerait toutes. La joignabilité se lit
             * — voir `GeoGuard::zoneEstJoignable()` — elle ne s'écrit pas.
             */
            Action::make('toggle-active', 'Activer / désactiver', function (Country $pays) {
                $pays->forceFill(['is_active' => ! $pays->is_active])->save();

                return ['is_active' => (bool) $pays->fresh()->is_active];
            }),
        ];
    }

    public function reasonsToRefuseDelete(Model $model): array
    {
        return app(GeoGuard::class)->raisonsDeNePasSupprimerPays($model);
    }
}

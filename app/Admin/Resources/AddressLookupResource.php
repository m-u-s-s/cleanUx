<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\AddressLookup;

/**
 * Le cache des recherches d’adresse.
 *
 * La PURGE du cache ne se déclenche pas d’ici : elle vide un cache que le calcul de distance et
 * le matching interrogent en permanence, et son coût se mesure sur la page qui montre le taux
 * de succès.
 *
 * @extends EloquentResource<AddressLookup>
 */
class AddressLookupResource extends EloquentResource
{
    public function key(): string
    {
        return 'geolocation';
    }

    protected function model(): string
    {
        return AddressLookup::class;
    }

    protected function columnSpec(): array
    {
        return [
            'query' => ['Recherche'],
            'provider' => ['Fournisseur', Column::TYPE_BADGE],
            'country_code' => ['Pays'],
            'result_count' => ['Résultats', Column::TYPE_NUMBER],
            'queried_at' => ['Interrogée le', Column::TYPE_DATETIME],
        ];
    }

    protected function searchable(): array
    {
        return ['query'];
    }

    protected function searchLabel(): string
    {
        return 'Adresse recherchée';
    }

    protected function detailSpec(): array
    {
        return [
            'expires_at' => 'Expire le',
        ];
    }
}

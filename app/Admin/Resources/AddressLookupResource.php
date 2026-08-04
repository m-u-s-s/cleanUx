<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\AddressLookup;
use App\Services\GeolocationV2\GeocodingService;

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

    public function globalActions(): array
    {
        return [
            /*
             * Purger le cache expiré. Global par nature : il ne vise aucune adresse en
             * particulier. Le nombre de lignes purgées est rendu — « purgé » sans chiffre ne dit
             * pas si le cache était plein ou déjà vide.
             */
            Action::make('purge-cache', 'Purger le cache expiré', function (array $valeurs) {
                $purge = app(GeocodingService::class)->purgeExpired();

                return ['purged' => array_sum($purge)];
            }),
        ];
    }
}

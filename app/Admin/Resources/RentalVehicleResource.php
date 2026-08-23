<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\RentalVehicle;

/**
 * NOS LOCATIONS — le parc, vu depuis la console d'administration mobile.
 *
 * @extends EloquentResource<RentalVehicle>
 */
class RentalVehicleResource extends EloquentResource
{
    public function key(): string
    {
        return 'rentals';
    }

    protected function model(): string
    {
        return RentalVehicle::class;
    }

    protected function columnSpec(): array
    {
        return [
            'brand' => ['Marque'],
            'model' => ['Modèle'],
            'category' => ['Catégorie', Column::TYPE_BADGE],
            'daily_price_cents' => ['Prix/jour (cents)', Column::TYPE_NUMBER],
            'is_active' => ['En vitrine', Column::TYPE_BADGE],
        ];
    }

    protected function searchable(): array
    {
        return ['brand', 'model', 'plate', 'code'];
    }

    protected function searchLabel(): string
    {
        return 'Marque, modèle, plaque ou code';
    }

    protected function selectFilters(): array
    {
        return [
            'category' => ['Catégorie', 'category', [
                ['value' => 'citadine', 'label' => 'Citadine'],
                ['value' => 'compacte', 'label' => 'Compacte'],
                ['value' => 'berline', 'label' => 'Berline'],
                ['value' => 'suv', 'label' => 'SUV'],
                ['value' => 'monospace', 'label' => 'Monospace'],
                ['value' => 'utilitaire', 'label' => 'Utilitaire'],
                ['value' => 'premium', 'label' => 'Premium'],
            ]],
            'transmission' => ['Boîte', 'transmission', [
                ['value' => RentalVehicle::TRANSMISSION_MANUELLE, 'label' => 'Manuelle'],
                ['value' => RentalVehicle::TRANSMISSION_AUTOMATIQUE, 'label' => 'Automatique'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'plate' => 'Plaque',
            'year' => 'Année',
            'fuel' => 'Énergie',
            'seats' => 'Places',
            'deposit_cents' => 'Caution (cents)',
            'waiver_daily_price_cents' => 'Garantie/jour (cents)',
            'waiver_deposit_cents' => 'Caution avec garantie (cents)',
            'min_driver_age' => 'Âge minimum',
            'min_license_years' => 'Permis (années)',
        ];
    }

    public function actions(): array
    {
        return [
            // RETIRER OU REMETTRE EN VITRINE — le seul geste qui mérite d'être mobile.
            Action::make('toggle-vitrine', 'Basculer la vitrine', function (RentalVehicle $vehicule, array $valeurs) {
                $vehicule->update(['is_active' => ! $vehicule->is_active]);

                return ['is_active' => $vehicule->is_active];
            }),
        ];
    }
}

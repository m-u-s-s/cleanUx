<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\RentalVehicle;

/**
 * NOS LOCATIONS — le parc, vu depuis la console d'administration mobile.
 *
 * À NE PAS CONFONDRE AVEC {@see FleetEquipmentResource}, juste à côté. Fleet décrit ce qu'une
 * société confie à ses exécutants pour aller travailler ; ici chaque véhicule est un PRODUIT vendu
 * à un client, avec un prix par jour, une caution et une garantie.
 *
 * ── CE QUE LE MOBILE PORTE, ET CE QU'IL NE PORTE PAS ─────────────────────────────────────────
 *
 * Il porte la consultation du parc et l'INTERRUPTEUR DE VITRINE, parce que c'est le geste qu'on
 * fait loin de son bureau : une voiture rentre accidentée, on la retire du catalogue depuis le
 * parking avant qu'un client ne la réserve.
 *
 * Il ne porte ni la saisie des tarifs, ni les médias. Composer une grille de prix ou déposer
 * trente-six photos de rotation sur un téléphone n'est pas un service qu'on rend à quelqu'un —
 * c'est du travail d'écran, et l'écran web existe.
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
            /*
             * RETIRER OU REMETTRE EN VITRINE — le seul geste qui mérite d'être mobile.
             *
             * Une voiture rentre accidentée : on la retire du catalogue depuis le parking, avant
             * qu'un client ne la réserve. Attendre d'être devant un ordinateur, c'est laisser une
             * fenêtre pendant laquelle la réservation reste possible.
             *
             * L'action bascule au lieu de forcer une valeur : le même bouton sert dans les deux
             * sens, et il n'y a pas d'état à deviner depuis le mobile.
             */
            Action::make('toggle-vitrine', 'Basculer la vitrine', function (RentalVehicle $vehicule, array $valeurs) {
                $vehicule->update(['is_active' => ! $vehicule->is_active]);

                return ['is_active' => $vehicule->is_active];
            }),
        ];
    }
}

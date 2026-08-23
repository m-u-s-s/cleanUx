<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\FleetEquipment;
use App\Services\FleetV2\CertificationExpiryScanner;

/**
 * Le matériel de la flotte.
 *
 * @extends EloquentResource<FleetEquipment>
 */
class FleetEquipmentResource extends EloquentResource
{
    public function key(): string
    {
        return 'fleet';
    }

    protected function model(): string
    {
        return FleetEquipment::class;
    }

    protected function columnSpec(): array
    {
        return [
            'name' => ['Équipement'],
            'equipment_type' => ['Type', Column::TYPE_BADGE],
            'status' => ['Statut', Column::TYPE_BADGE],
            'serial_number' => ['Numéro de série'],
            'value_cents' => ['Valeur (cents)', Column::TYPE_NUMBER],
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'code', 'serial_number', 'brand'];
    }

    protected function searchLabel(): string
    {
        return 'Nom, code, série ou marque';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'available', 'label' => 'Disponible'],
                ['value' => 'assigned', 'label' => 'Affecté'],
                ['value' => 'maintenance', 'label' => 'En maintenance'],
                ['value' => 'retired', 'label' => 'Retiré'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'brand' => 'Marque',
            'model' => 'Modèle',
            'current_location' => 'Emplacement',
            'warranty_expires_at' => 'Garantie jusqu’au',
        ];
    }

    public function globalActions(): array
    {
        return [
            // Balayer les certifications qui expirent.
            Action::make('scan-expiring', 'Balayer les certifications', function (array $valeurs) {
                $comptes = app(CertificationExpiryScanner::class)->scanAndUpdate();

                return ['updated' => array_sum($comptes)];
            }),
        ];
    }
}

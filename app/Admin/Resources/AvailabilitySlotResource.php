<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\AvailabilitySlot;

/**
 * Les créneaux de disponibilité récurrents des prestataires.
 *
 * Les EXCEPTIONS et les réservations temporaires vivent dans leurs propres tables : un créneau
 * récurrent dit ce qui est possible en général, pas ce qui reste libre aujourd’hui. Confondre
 * les deux ferait proposer des heures déjà prises.
 *
 * @extends EloquentResource<AvailabilitySlot>
 */
class AvailabilitySlotResource extends EloquentResource
{
    public function key(): string
    {
        return 'availability';
    }

    protected function model(): string
    {
        return AvailabilitySlot::class;
    }

    protected function columnSpec(): array
    {
        return [
            'weekday' => ['Jour', Column::TYPE_NUMBER],
            'start_time' => ['Début'],
            'end_time' => ['Fin'],
            'is_active' => ['Actif', Column::TYPE_BOOL],
            'valid_until' => ['Valide jusqu’au', Column::TYPE_DATE],
        ];
    }

    protected function searchable(): array
    {
        return ['timezone'];
    }

    protected function searchLabel(): string
    {
        return 'Fuseau horaire';
    }

    protected function detailSpec(): array
    {
        return [
            'valid_from' => 'Valide à partir du',
            'timezone' => 'Fuseau',
        ];
    }
}

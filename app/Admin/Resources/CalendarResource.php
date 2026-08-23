<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\Booking;

/**
 * La vue calendrier des interventions.
 *
 * @extends EloquentResource<Booking>
 */
class CalendarResource extends EloquentResource
{
    public function key(): string
    {
        return 'calendar';
    }

    protected function model(): string
    {
        return Booking::class;
    }

    protected function columnSpec(): array
    {
        return [
            'scheduled_date' => ['Date', Column::TYPE_DATE],
            'scheduled_time' => ['Heure'],
            'booking_reference' => ['Référence'],
            'status' => ['Statut', Column::TYPE_BADGE],
            'frequency' => ['Récurrence', Column::TYPE_BADGE],
        ];
    }

    protected function searchable(): array
    {
        return ['booking_reference', 'city'];
    }

    protected function searchLabel(): string
    {
        return 'Référence ou ville';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'en_attente', 'label' => 'En attente'],
                ['value' => 'confirme', 'label' => 'Confirmé'],
                ['value' => 'en_route', 'label' => 'En route'],
                ['value' => 'sur_place', 'label' => 'Sur place'],
                ['value' => 'termine', 'label' => 'Terminé'],
                ['value' => 'annule', 'label' => 'Annulé'],
                ['value' => 'refuse', 'label' => 'Refusé'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'city' => 'Ville',
            'address' => 'Adresse',
            'is_recurrent' => 'Récurrent',
        ];
    }
}

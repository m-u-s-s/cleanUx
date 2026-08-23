<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\Booking;

/**
 * Le planning : les réservations à venir, triées par date.
 *
 * @extends EloquentResource<Booking>
 */
class PlanningResource extends EloquentResource
{
    public function key(): string
    {
        return 'planning';
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
            'status' => ['Statut', Column::TYPE_BADGE],
            'city' => ['Ville'],
            'booking_reference' => ['Référence'],
        ];
    }

    protected function searchable(): array
    {
        return ['booking_reference', 'city', 'contact_name'];
    }

    protected function searchLabel(): string
    {
        return 'Référence, ville ou contact';
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
            'address' => 'Adresse',
            'contact_phone' => 'Téléphone',
            'estimated_duration_minutes' => 'Durée estimée (min)',
        ];
    }
}

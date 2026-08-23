<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\Booking;

/**
 * Les réservations, toutes confondues. NI ANNULATION NI REPROGRAMMATION ICI.
 *
 * @extends EloquentResource<Booking>
 */
class BookingResource extends EloquentResource
{
    public function key(): string
    {
        return 'bookings';
    }

    protected function model(): string
    {
        return Booking::class;
    }

    protected function columnSpec(): array
    {
        return [
            'booking_reference' => ['Référence'],
            'status' => ['Statut', Column::TYPE_BADGE],
            'scheduled_date' => ['Date', Column::TYPE_DATE],
            'city' => ['Ville'],
            'estimated_price' => ['Prix estimé', Column::TYPE_MONEY],
        ];
    }

    protected function searchable(): array
    {
        return ['booking_reference', 'city', 'address', 'contact_name'];
    }

    protected function searchLabel(): string
    {
        return 'Référence, ville, adresse ou contact';
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
            'contact_name' => 'Contact',
            'contact_phone' => 'Téléphone',
            'address' => 'Adresse',
            'customer_comment' => 'Commentaire client',
            'booking_mode' => 'Mode',
        ];
    }
}

<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\Booking;

/**
 * Les réservations annulées et leur motif.
 *
 * Sert à voir CE QUI REVIENT dans les annulations. Les agrégats et l’évolution dans le temps
 * restent sur la page d’analyse : une liste répond à « qu’est-ce qui s’est passé ici », pas à
 * « est-ce que ça empire ».
 *
 * @extends EloquentResource<Booking>
 */
class CancellationReasonResource extends EloquentResource
{
    public function key(): string
    {
        return 'cancellation-reasons';
    }

    protected function model(): string
    {
        return Booking::class;
    }

    protected function columnSpec(): array
    {
        return [
            'booking_reference' => ['Référence'],
            'cancellation_reason' => ['Motif'],
            'cancelled_at' => ['Annulée le', Column::TYPE_DATETIME],
            'status' => ['Statut', Column::TYPE_BADGE],
            'scheduled_date' => ['Prévue le', Column::TYPE_DATE],
        ];
    }

    protected function searchable(): array
    {
        return ['cancellation_reason', 'booking_reference'];
    }

    protected function searchLabel(): string
    {
        return 'Motif ou référence';
    }

    protected function detailSpec(): array
    {
        return [
            'city' => 'Ville',
            'estimated_price' => 'Prix estimé',
        ];
    }
}

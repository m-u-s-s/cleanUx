<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\BookingTip;

/**
 * Les pourboires laisses aux prestataires.
 *
 * Aucun remboursement depuis la console : un pourboire remboursé touche à l’argent d’un
 * prestataire déjà crédité, et ce chemin passe par le module de remboursement qui tient le
 * registre et la reprise proportionnelle.
 *
 * @extends EloquentResource<BookingTip>
 */
class TipResource extends EloquentResource
{
    public function key(): string
    {
        return 'tips';
    }

    protected function model(): string
    {
        return BookingTip::class;
    }

    protected function columnSpec(): array
    {
        return [
            'code' => ['Référence'],
            'amount_cents' => ['Montant (cents)', Column::TYPE_NUMBER],
            'status' => ['Statut', Column::TYPE_BADGE],
            'preset_label' => ['Preset'],
            'created_at' => ['Laissé le', Column::TYPE_DATE],
        ];
    }

    protected function searchable(): array
    {
        return ['code', 'message'];
    }

    protected function searchLabel(): string
    {
        return 'Référence ou message';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'pending', 'label' => 'En attente'],
                ['value' => 'charged', 'label' => 'Prélevé'],
                ['value' => 'paid_out', 'label' => 'Reversé'],
                ['value' => 'refunded', 'label' => 'Remboursé'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'message' => 'Message',
            'currency' => 'Devise',
            'charged_at' => 'Prélevé le',
            'paid_out_at' => 'Reversé le',
        ];
    }
}

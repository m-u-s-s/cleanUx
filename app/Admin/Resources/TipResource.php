<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\BookingTip;
use App\Services\Tips\TipService;

/**
 * Les pourboires laisses aux prestataires.
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

    public function actions(): array
    {
        return [
            // Les trois gestes de rattrapage d'un pourboire.
            Action::make('confirm', 'Marquer chargé', function (BookingTip $tip) {
                app(TipService::class)->confirmCharge($tip, 'manual_admin_'.$tip->id);

                return ['ok' => true];
            }),

            Action::make('mark-paid-out', 'Marquer reversé', function (BookingTip $tip) {
                app(TipService::class)->markPaidOut($tip, 'manual_payout_'.$tip->id);

                return ['ok' => true];
            }),

            Action::make('mark-failed', 'Marquer en échec', function (BookingTip $tip) {
                app(TipService::class)->markFailed($tip, 'admin_manual_fail');

                return ['ok' => true];
            })->destructive('Le pourboire sera marqué en échec.'),
        ];
    }
}

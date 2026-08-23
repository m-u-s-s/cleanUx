<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\ContractRateCard;

/**
 * Les grilles tarifaires négociées par contrat B2B.
 *
 * @extends EloquentResource<ContractRateCard>
 */
class ContractRateCardResource extends EloquentResource
{
    public function key(): string
    {
        return 'b2b-operations';
    }

    protected function model(): string
    {
        return ContractRateCard::class;
    }

    protected function columnSpec(): array
    {
        return [
            'negotiated_unit_price_cents' => ['Prix négocié (cents)', Column::TYPE_NUMBER],
            'currency' => ['Devise', Column::TYPE_BADGE],
            'created_at' => ['Créée le', Column::TYPE_DATE],
        ];
    }

    protected function searchable(): array
    {
        return ['currency'];
    }

    protected function searchLabel(): string
    {
        return 'Devise';
    }

    protected function detailSpec(): array
    {
        return [
            'updated_at' => 'Modifiée le',
        ];
    }
}

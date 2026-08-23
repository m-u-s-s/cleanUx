<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Admin\Console\Field;
use App\Models\InsuranceClaim;

/**
 * Les sinistres déclarés.
 *
 * @extends EloquentResource<InsuranceClaim>
 */
class InsuranceClaimResource extends EloquentResource
{
    public function key(): string
    {
        return 'insurance';
    }

    protected function model(): string
    {
        return InsuranceClaim::class;
    }

    protected function columnSpec(): array
    {
        return [
            'incident_type' => ['Type', Column::TYPE_BADGE],
            'status' => ['Statut', Column::TYPE_BADGE],
            'amount_claimed_cents' => ['Réclamé (cents)', Column::TYPE_NUMBER],
            'amount_settled_cents' => ['Indemnisé (cents)', Column::TYPE_NUMBER],
            'filed_at' => ['Déclaré le', Column::TYPE_DATETIME],
        ];
    }

    protected function searchable(): array
    {
        return ['incident_description', 'external_claim_id'];
    }

    protected function searchLabel(): string
    {
        return 'Description ou référence externe';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'filed', 'label' => 'Déclaré'],
                ['value' => 'reviewing', 'label' => 'En instruction'],
                ['value' => 'approved', 'label' => 'Approuvé'],
                ['value' => 'rejected', 'label' => 'Refusé'],
                ['value' => 'paid', 'label' => 'Indemnisé'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'incident_description' => 'Description',
            'decision_reason' => 'Motif de décision',
            'incident_date' => 'Date du sinistre',
            'paid_at' => 'Indemnisé le',
        ];
    }

    public function actions(): array
    {
        return [
            // Le statut d'un sinistre suit une LISTE FERMÉE, celle du web.
            Action::make('set-status', 'Changer le statut', function (InsuranceClaim $claim, array $valeurs) {
                $claim->forceFill(['status' => (string) $valeurs['status']])->save();

                return ['status' => $claim->fresh()->status];
            })->requires([
                Field::select('status', 'Nouveau statut', [
                    ['value' => InsuranceClaim::STATUS_UNDER_REVIEW, 'label' => 'En examen'],
                    ['value' => InsuranceClaim::STATUS_INFO_REQUESTED, 'label' => 'Complément demandé'],
                    ['value' => InsuranceClaim::STATUS_ACCEPTED, 'label' => 'Accepté'],
                    ['value' => InsuranceClaim::STATUS_REJECTED, 'label' => 'Refusé'],
                    ['value' => InsuranceClaim::STATUS_PAID, 'label' => 'Indemnisé'],
                ])->rules(['required', 'string', 'max:40']),
            ]),
        ];
    }
}

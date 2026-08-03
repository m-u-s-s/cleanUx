<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\InsuranceClaim;

/**
 * Les sinistres déclarés.
 *
 * La MACHINE À ÉTATS d’un sinistre est tenue par le module Assurance : elle refuse les
 * transitions incohérentes. Écrire un statut ici sauterait une étape et laisserait un sinistre
 * indemnisé sans avoir été instruit.
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
}

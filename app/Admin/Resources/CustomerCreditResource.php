<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\CustomerCredit;

/**
 * Les avoirs et crédits clients.
 *
 * ATTENTION, PIÈGE CONNU DE CE PROJET : le modèle et la table de cette entité ont divergé par le
 * passé. Les colonnes servies ici sont celles du SCHÉMA, vérifiées — et le test de schéma le
 * refera à chaque exécution.
 *
 * Aucune création depuis la console : un avoir naît d’un remboursement ou d’un geste commercial,
 * tous deux journalisés par leur module.
 *
 * @extends EloquentResource<CustomerCredit>
 */
class CustomerCreditResource extends EloquentResource
{
    public function key(): string
    {
        return 'credits';
    }

    protected function model(): string
    {
        return CustomerCredit::class;
    }

    protected function columnSpec(): array
    {
        return [
            'type' => ['Type', Column::TYPE_BADGE],
            'amount' => ['Montant', Column::TYPE_MONEY],
            'remaining_amount' => ['Restant', Column::TYPE_MONEY],
            'status' => ['Statut', Column::TYPE_BADGE],
            'expires_at' => ['Expire le', Column::TYPE_DATE],
        ];
    }

    protected function searchable(): array
    {
        return ['reason', 'notes'];
    }

    protected function searchLabel(): string
    {
        return 'Motif ou notes';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'active', 'label' => 'Actif'],
                ['value' => 'used', 'label' => 'Utilisé'],
                ['value' => 'expired', 'label' => 'Expiré'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'reason' => 'Motif',
            'notes' => 'Notes',
        ];
    }
}

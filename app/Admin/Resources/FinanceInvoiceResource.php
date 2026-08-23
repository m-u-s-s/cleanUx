<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\FinanceInvoice;

/**
 * Les factures, dont la facturation mensuelle B2B.
 *
 * @extends EloquentResource<FinanceInvoice>
 */
class FinanceInvoiceResource extends EloquentResource
{
    public function key(): string
    {
        return 'b2b-invoices';
    }

    protected function model(): string
    {
        return FinanceInvoice::class;
    }

    protected function columnSpec(): array
    {
        return [
            'invoice_number' => ['Numéro'],
            'status' => ['Statut', Column::TYPE_BADGE],
            'total_amount' => ['Total', Column::TYPE_MONEY],
            'balance_due' => ['Reste dû', Column::TYPE_MONEY],
            'issued_at' => ['Émise le', Column::TYPE_DATE],
        ];
    }

    protected function searchable(): array
    {
        return ['invoice_number'];
    }

    protected function searchLabel(): string
    {
        return 'Numéro de facture';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'draft', 'label' => 'Brouillon'],
                ['value' => 'issued', 'label' => 'Émise'],
                ['value' => 'paid', 'label' => 'Payée'],
                ['value' => 'overdue', 'label' => 'En retard'],
                ['value' => 'cancelled', 'label' => 'Annulée'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'due_at' => 'Échéance',
            'paid_amount' => 'Payé',
            'tax_amount' => 'TVA',
            'currency' => 'Devise',
        ];
    }
}

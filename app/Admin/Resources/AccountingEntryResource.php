<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\AccountingEntry;

/**
 * Le grand livre comptable.
 *
 * LECTURE SEULE, et c’est le point le plus important de tout ce moteur. Le registre est en
 * partie double et IMMUABLE : une écriture ne se modifie ni ne s’efface, elle se contre-passe.
 * Un bouton de suppression ici casserait l’équilibre débit-crédit sans laisser de trace.
 *
 * @extends EloquentResource<AccountingEntry>
 */
class AccountingEntryResource extends EloquentResource
{
    public function key(): string
    {
        return 'accounting';
    }

    protected function model(): string
    {
        return AccountingEntry::class;
    }

    protected function columnSpec(): array
    {
        return [
            'entry_code' => ['Écriture'],
            'account_code' => ['Compte'],
            'debit_cents' => ['Débit (cents)', Column::TYPE_NUMBER],
            'credit_cents' => ['Crédit (cents)', Column::TYPE_NUMBER],
            'posting_date' => ['Date', Column::TYPE_DATE],
        ];
    }

    protected function searchable(): array
    {
        return ['entry_code', 'account_code', 'account_name', 'label'];
    }

    protected function searchLabel(): string
    {
        return 'Écriture, compte ou libellé';
    }

    protected function selectFilters(): array
    {
        return [
            'journal_code' => ['Journal', 'journal_code', [
                ['value' => 'VE', 'label' => 'Ventes'],
                ['value' => 'AC', 'label' => 'Achats'],
                ['value' => 'BQ', 'label' => 'Banque'],
                ['value' => 'OD', 'label' => 'Opérations diverses'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'label' => 'Libellé',
            'reference' => 'Référence',
            'vat_amount_cents' => 'TVA (cents)',
            'currency' => 'Devise',
        ];
    }
}

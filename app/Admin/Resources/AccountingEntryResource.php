<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Admin\Console\Field;
use App\Models\AccountingEntry;
use App\Services\AccountingV2\PeriodCloser;

/**
 * Le grand livre comptable. LECTURE SEULE, et c’est le point le plus important de tout ce moteur.
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

    public function globalActions(): array
    {
        return [
            // Clôturer un mois.
            Action::make('close-period', 'Clôturer une période', function (array $valeurs) {
                app(PeriodCloser::class)->close(
                    (int) $valeurs['year'],
                    (int) $valeurs['month'],
                    request()->user(),
                );

                return ['ok' => true];
            })->requires([
                Field::make('year', 'Année', Field::TYPE_NUMBER)->rules(['required', 'integer', 'min:2020', 'max:2100']),
                Field::make('month', 'Mois', Field::TYPE_NUMBER)->rules(['required', 'integer', 'min:1', 'max:12']),
            ])->destructive('La période sera close : plus aucune écriture ne pourra y être passée.'),
        ];
    }
}

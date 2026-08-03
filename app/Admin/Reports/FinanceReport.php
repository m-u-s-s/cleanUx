<?php

namespace App\Admin\Reports;

use App\Admin\Console\AdminReport;
use App\Admin\Console\ReportTile;
use App\Models\AccountingEntry;
use App\Models\FinanceInvoice;

/**
 * La santé financière.
 *
 * Le RESTE DÛ est en ton d’alerte dès qu’il n’est pas nul : c’est de l’argent facturé qui n’est
 * pas rentré, et un tableau de bord financier qui l’affiche en neutre laisse passer le seul
 * chiffre qu’on ouvre cette page pour voir.
 */
class FinanceReport implements AdminReport
{
    public function key(): string
    {
        return 'finance';
    }

    public function sections(): array
    {
        return [
            [
                'title' => 'Facturation',
                'tiles' => [
                    ReportTile::make(
                        'invoices',
                        'Factures',
                        fn () => FinanceInvoice::count(),
                    ),
                    ReportTile::make(
                        'outstanding',
                        'Reste dû',
                        fn () => (float) FinanceInvoice::sum('balance_due'),
                        format: 'money',
                        tone: fn ($v) => $v > 0 ? ReportTile::TONE_WARNING : ReportTile::TONE_SUCCESS,
                    ),
                ],
            ],
            [
                'title' => 'Comptabilité',
                'tiles' => [
                    ReportTile::make(
                        'entries',
                        'Écritures comptables',
                        fn () => AccountingEntry::count(),
                    ),
                ],
            ],
        ];
    }
}

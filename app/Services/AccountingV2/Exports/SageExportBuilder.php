<?php

namespace App\Services\AccountingV2\Exports;

use Illuminate\Database\Eloquent\Builder;

/**
 * Sage import (Format BNC/PNM simplifié — CSV avec colonnes propres).
 * Skeleton — variantes Sage 100, Sage Saari, Sage 50c possibles.
 * Cette implémentation suit le format CSV générique compatible Sage Import Plus.
 */
class SageExportBuilder implements ExportBuilderContract
{
    public function format(): string
    {
        return 'sage';
    }

    public function build(Builder $entriesQuery, array $opts = []): array
    {
        $delimiter = ';';
        $cols = ['Date', 'Journal', 'Compte', 'Libelle', 'Debit', 'Credit', 'Piece', 'Devise'];
        $rows = [];
        $rows[] = implode($delimiter, $cols);
        $count = 0;

        $entriesQuery->orderBy('posting_date')->orderBy('id')->chunk(500, function ($entries) use (&$rows, &$count, $delimiter) {
            foreach ($entries as $e) {
                $row = [
                    $e->posting_date->format('d/m/Y'),
                    $e->journal_code,
                    $e->account_code,
                    str_replace([';', "\n", "\r"], ' ', (string) $e->label),
                    number_format($e->debit_cents / 100, 2, ',', ''),
                    number_format($e->credit_cents / 100, 2, ',', ''),
                    (string) ($e->reference ?? ''),
                    $e->currency,
                ];
                $rows[] = implode($delimiter, $row);
                $count++;
            }
        });

        return [
            'content' => implode("\r\n", $rows),
            'row_count' => $count,
            'mime' => 'text/csv',
            'extension' => 'sage.csv',
        ];
    }
}

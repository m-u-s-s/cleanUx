<?php

namespace App\Services\AccountingV2\Exports;

use Illuminate\Database\Eloquent\Builder;

/**
 * FEC = Fichier des Écritures Comptables (norme DGFiP FR).
 * Spec : 18 colonnes pipe-separated dans cet ordre exact.
 * Doc : https://www.economie.gouv.fr/dgfip/le-fichier-des-ecritures-comptables-fec
 */
class FecExportBuilder implements ExportBuilderContract
{
    public function format(): string
    {
        return 'fec';
    }

    public function build(Builder $entriesQuery, array $opts = []): array
    {
        $delimiter = (string) (config('accounting_v2.fec.delimiter') ?: '|');
        $cols = [
            'JournalCode', 'JournalLib', 'EcritureNum', 'EcritureDate',
            'CompteNum', 'CompteLib', 'CompAuxNum', 'CompAuxLib',
            'PieceRef', 'PieceDate', 'EcritureLib',
            'Debit', 'Credit',
            'EcritureLet', 'DateLet',
            'ValidDate', 'Montantdevise', 'Idevise',
        ];

        $rows = [];
        $rows[] = implode($delimiter, $cols);
        $count = 0;
        $journals = (array) config('accounting_v2.journals', []);

        $entriesQuery->orderBy('posting_date')->orderBy('id')->chunk(500, function ($entries) use (&$rows, &$count, $delimiter, $journals) {
            foreach ($entries as $e) {
                $journalLib = $journals[$e->journal_code]['name'] ?? $e->journal_code;
                $row = [
                    $this->sanitize($e->journal_code),
                    $this->sanitize($journalLib),
                    $this->sanitize($e->batch_id),
                    $e->posting_date->format('Ymd'),
                    $this->sanitize($e->account_code),
                    $this->sanitize((string) $e->account_name),
                    $this->sanitize($e->counterparty_type ? $e->counterparty_type . '_' . $e->counterparty_id : ''),
                    '',
                    $this->sanitize((string) $e->reference),
                    $e->posting_date->format('Ymd'),
                    $this->sanitize($e->label),
                    $this->formatAmount($e->debit_cents),
                    $this->formatAmount($e->credit_cents),
                    '',
                    '',
                    $e->created_at?->format('Ymd') ?? '',
                    '',
                    $this->sanitize($e->currency),
                ];
                $rows[] = implode($delimiter, $row);
                $count++;
            }
        });

        return [
            'content' => implode("\r\n", $rows),
            'row_count' => $count,
            'mime' => 'text/plain',
            'extension' => 'fec.txt',
        ];
    }

    protected function sanitize(string $value): string
    {
        // Le FEC interdit pipe, retour ligne et tab dans les valeurs.
        return str_replace(['|', "\r", "\n", "\t"], ' ', $value);
    }

    protected function formatAmount(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '');
    }
}

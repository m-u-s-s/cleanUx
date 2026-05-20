<?php

namespace App\Services\AccountingV2\Exports;

use Illuminate\Database\Eloquent\Builder;

class CsvExportBuilder implements ExportBuilderContract
{
    public function format(): string
    {
        return 'csv';
    }

    public function build(Builder $entriesQuery, array $opts = []): array
    {
        $delimiter = (string) ($opts['delimiter'] ?? ';');
        $cols = [
            'entry_code', 'posting_date', 'journal_code',
            'account_code', 'account_name',
            'debit_cents', 'credit_cents', 'currency',
            'label', 'reference', 'batch_id',
            'vat_rate', 'vat_amount_cents',
            'source_type', 'source_id',
        ];

        $rows = [];
        $rows[] = $this->joinCsv($cols, $delimiter);
        $count = 0;

        $entriesQuery->orderBy('posting_date')->orderBy('id')->chunk(500, function ($entries) use (&$rows, &$count, $cols, $delimiter) {
            foreach ($entries as $e) {
                $row = [];
                foreach ($cols as $col) {
                    $value = $e->{$col};
                    if ($col === 'posting_date' && $value) {
                        $value = $e->posting_date->format('Y-m-d');
                    }
                    $row[] = (string) ($value ?? '');
                }
                $rows[] = $this->joinCsv($row, $delimiter);
                $count++;
            }
        });

        return [
            'content' => implode("\r\n", $rows),
            'row_count' => $count,
            'mime' => 'text/csv',
            'extension' => 'csv',
        ];
    }

    protected function joinCsv(array $row, string $delimiter): string
    {
        return implode($delimiter, array_map(function ($v) use ($delimiter) {
            $s = (string) $v;
            if (str_contains($s, $delimiter) || str_contains($s, '"') || str_contains($s, "\n")) {
                $s = '"' . str_replace('"', '""', $s) . '"';
            }
            return $s;
        }, $row));
    }
}

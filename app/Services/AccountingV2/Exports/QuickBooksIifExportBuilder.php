<?php

namespace App\Services\AccountingV2\Exports;

use Illuminate\Database\Eloquent\Builder;

/**
 * QuickBooks IIF (Intuit Interchange Format).
 * Spec : tab-separated, headers !TRNS / !SPL / !ENDTRNS, transactions groupées par batch.
 * Skeleton suffisant pour import QuickBooks Desktop / Online (via converter).
 */
class QuickBooksIifExportBuilder implements ExportBuilderContract
{
    public function format(): string
    {
        return 'quickbooks_iif';
    }

    public function build(Builder $entriesQuery, array $opts = []): array
    {
        $headerTrns = "!TRNS\tTRNSID\tTRNSTYPE\tDATE\tACCNT\tAMOUNT\tDOCNUM\tMEMO";
        $headerSpl = "!SPL\tSPLID\tTRNSTYPE\tDATE\tACCNT\tAMOUNT\tDOCNUM\tMEMO";
        $headerEnd = "!ENDTRNS";

        $rows = [$headerTrns, $headerSpl, $headerEnd];
        $count = 0;

        $batches = [];
        $entriesQuery->orderBy('batch_id')->orderBy('id')->chunk(500, function ($entries) use (&$batches) {
            foreach ($entries as $e) {
                $batches[$e->batch_id] ??= [];
                $batches[$e->batch_id][] = $e;
            }
        });

        foreach ($batches as $batchId => $lines) {
            $first = $lines[0];
            $date = $first->posting_date->format('m/d/Y');
            $reference = (string) ($first->reference ?? $batchId);
            $primary = collect($lines)->firstWhere('debit_cents', '>', 0) ?? $first;
            $primaryAmount = number_format(($primary->debit_cents - $primary->credit_cents) / 100, 2, '.', '');

            $rows[] = implode("\t", [
                'TRNS', $batchId, 'GENERAL JOURNAL', $date,
                $primary->account_code, $primaryAmount, $reference,
                str_replace("\t", ' ', (string) $first->label),
            ]);

            foreach ($lines as $idx => $line) {
                if ($line->id === $primary->id) {
                    continue;
                }
                $amount = number_format(($line->credit_cents - $line->debit_cents) / 100, 2, '.', '');
                $rows[] = implode("\t", [
                    'SPL', $batchId . '_' . $idx, 'GENERAL JOURNAL', $date,
                    $line->account_code, $amount, $reference,
                    str_replace("\t", ' ', (string) $line->label),
                ]);
            }
            $rows[] = 'ENDTRNS';
            $count += count($lines);
        }

        return [
            'content' => implode("\r\n", $rows),
            'row_count' => $count,
            'mime' => 'text/plain',
            'extension' => 'iif',
        ];
    }
}

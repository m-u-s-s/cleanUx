<?php

namespace App\Services\AccountingV2;

use App\Models\AccountingEntry;
use App\Models\AccountingPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingService
{
    public function __construct(protected ChartOfAccounts $chart) {}

    /**
     * Poste une transaction comptable (1 batch = N lignes équilibrées).
     *
     * @param array<int, array{
     *   account_code:string, debit_cents?:int, credit_cents?:int,
     *   label:string, journal_code?:string, reference?:string,
     *   vat_rate?:float, vat_amount_cents?:int,
     *   counterparty_type?:string, counterparty_id?:int,
     *   metadata?:array,
     * }> $lines
     *
     * @return string batch_id généré
     */
    public function post(array $lines, array $opts = []): string
    {
        if (count($lines) < 2) {
            throw ValidationException::withMessages(['lines' => ['Une transaction doit avoir au moins 2 lignes.']]);
        }
        $postingDate = $opts['posting_date'] ?? now()->toDateString();
        if (! ($postingDate instanceof Carbon)) {
            $postingDate = Carbon::parse($postingDate);
        }

        if ((bool) config('accounting_v2.block_post_on_closed_period', true)) {
            $this->guardClosedPeriod($postingDate);
        }

        $journal = (string) ($opts['journal_code'] ?? 'OD');
        if (! in_array($journal, (array) config('accounting_v2.allowed_journals', []), true)) {
            throw ValidationException::withMessages(['journal_code' => ['Journal inconnu.']]);
        }

        // Compute balance + validate
        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($lines as $i => $l) {
            $d = (int) ($l['debit_cents'] ?? 0);
            $c = (int) ($l['credit_cents'] ?? 0);
            if ($d < 0 || $c < 0) {
                throw ValidationException::withMessages(["lines.$i" => ['Montants négatifs non autorisés.']]);
            }
            if ($d > 0 && $c > 0) {
                throw ValidationException::withMessages(["lines.$i" => ['Une ligne ne peut avoir débit ET crédit.']]);
            }
            if ($d === 0 && $c === 0) {
                throw ValidationException::withMessages(["lines.$i" => ['Ligne sans montant.']]);
            }
            $code = (string) ($l['account_code'] ?? '');
            if (! $this->chart->exists($code)) {
                throw ValidationException::withMessages(["lines.$i" => ["Compte inconnu : {$code}"]]);
            }
            $totalDebit += $d;
            $totalCredit += $c;
        }
        if ($totalDebit !== $totalCredit) {
            throw ValidationException::withMessages([
                'lines' => ["Batch non équilibré : débit={$totalDebit}, crédit={$totalCredit}"],
            ]);
        }

        $batchId = AccountingEntry::generateBatchId();
        $now = now();
        $reference = $opts['reference'] ?? null;
        $currency = (string) ($opts['currency'] ?? 'EUR');
        $sourceType = $opts['source_type'] ?? null;
        $sourceId = $opts['source_id'] ?? null;
        $postedByUserId = $opts['posted_by_user_id'] ?? null;

        DB::transaction(function () use ($lines, $batchId, $journal, $postingDate, $now, $reference, $currency, $sourceType, $sourceId, $postedByUserId, $opts) {
            foreach ($lines as $l) {
                AccountingEntry::query()->create([
                    'entry_code' => AccountingEntry::generateEntryCode(),
                    'batch_id' => $batchId,
                    'posting_date' => $postingDate,
                    'journal_code' => $l['journal_code'] ?? $journal,
                    'account_code' => $l['account_code'],
                    'account_name' => $this->chart->name($l['account_code']),
                    'debit_cents' => (int) ($l['debit_cents'] ?? 0),
                    'credit_cents' => (int) ($l['credit_cents'] ?? 0),
                    'label' => mb_substr((string) ($l['label'] ?? ''), 0, 500),
                    'reference' => $l['reference'] ?? $reference,
                    'currency' => $l['currency'] ?? $currency,
                    'exchange_rate' => $l['exchange_rate'] ?? null,
                    'vat_rate' => $l['vat_rate'] ?? null,
                    'vat_amount_cents' => $l['vat_amount_cents'] ?? null,
                    'source_type' => $l['source_type'] ?? $sourceType,
                    'source_id' => $l['source_id'] ?? $sourceId,
                    'posted_by_user_id' => $l['posted_by_user_id'] ?? $postedByUserId,
                    'counterparty_type' => $l['counterparty_type'] ?? null,
                    'counterparty_id' => $l['counterparty_id'] ?? null,
                    'metadata' => $l['metadata'] ?? null,
                ]);
            }
        });

        return $batchId;
    }

    /**
     * Idempotence par source : si un batch existe déjà avec ces (source_type, source_id),
     * retourne le batch_id existant sans repost.
     */
    public function postIdempotent(string $sourceType, int $sourceId, array $lines, array $opts = []): string
    {
        $existing = AccountingEntry::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->orderBy('id')
            ->first();
        if ($existing) {
            return $existing->batch_id;
        }
        $opts['source_type'] = $sourceType;
        $opts['source_id'] = $sourceId;
        return $this->post($lines, $opts);
    }

    protected function guardClosedPeriod(Carbon $postingDate): void
    {
        $period = AccountingPeriod::query()
            ->where('period_year', (int) $postingDate->year)
            ->where('period_month', (int) $postingDate->month)
            ->where('is_closed', true)
            ->first();
        if ($period) {
            throw ValidationException::withMessages([
                'posting_date' => ['Période ' . $period->label() . ' est fermée — impossible de poster.'],
            ]);
        }
    }

    public function balanceForAccount(string $accountCode, ?int $year = null, ?int $month = null): array
    {
        $q = AccountingEntry::query()->where('account_code', $accountCode);
        if ($year) {
            $q->forPeriod($year, $month);
        }
        $rows = $q->get(['debit_cents', 'credit_cents']);
        $debit = (int) $rows->sum('debit_cents');
        $credit = (int) $rows->sum('credit_cents');
        return [
            'debit_cents' => $debit,
            'credit_cents' => $credit,
            'balance_cents' => $debit - $credit,
        ];
    }
}

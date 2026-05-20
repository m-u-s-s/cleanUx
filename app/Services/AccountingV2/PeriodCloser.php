<?php

namespace App\Services\AccountingV2;

use App\Models\AccountingEntry;
use App\Models\AccountingPeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PeriodCloser
{
    /**
     * Ferme une période : calcule totals + freeze + audit user. Inviolable après.
     */
    public function close(int $year, int $month, ?User $by = null): AccountingPeriod
    {
        $period = AccountingPeriod::query()->updateOrCreate(
            ['period_year' => $year, 'period_month' => $month],
            ['opened_at' => now()->startOfMonth()],
        );
        if ($period->is_closed) {
            throw ValidationException::withMessages([
                'period' => ['Période déjà fermée le ' . $period->closed_at?->toIso8601String()],
            ]);
        }

        $entries = AccountingEntry::query()->forPeriod($year, $month);
        $totalDebit = (int) $entries->sum('debit_cents');
        $totalCredit = (int) $entries->sum('credit_cents');
        if ($totalDebit !== $totalCredit) {
            throw ValidationException::withMessages([
                'period' => ["Période non équilibrée (debit={$totalDebit} credit={$totalCredit}) — refus de clôture."],
            ]);
        }
        $count = (int) AccountingEntry::query()->forPeriod($year, $month)->count();

        $totalsByAccount = AccountingEntry::query()
            ->forPeriod($year, $month)
            ->selectRaw('account_code, SUM(debit_cents) as d, SUM(credit_cents) as c')
            ->groupBy('account_code')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->account_code => ['debit' => (int) $r->d, 'credit' => (int) $r->c]])
            ->all();

        DB::transaction(function () use ($period, $totalDebit, $totalCredit, $count, $totalsByAccount, $by) {
            $period->update([
                'is_closed' => true,
                'closed_at' => now(),
                'closed_by_user_id' => $by?->id,
                'total_debit_cents' => $totalDebit,
                'total_credit_cents' => $totalCredit,
                'entry_count' => $count,
                'totals_by_account' => $totalsByAccount,
            ]);
        });

        \App\Support\Audit\CriticalActionAuditor::record(
            eventType: 'accounting.period_closed',
            context: [
                'period_year' => $period->period_year,
                'period_month' => $period->period_month,
                'entry_count' => $count,
                'total_debit_cents' => $totalDebit,
                'total_credit_cents' => $totalCredit,
            ],
            subject: $period,
            actor: $by,
            severity: 'warning',
        );

        return $period->fresh();
    }

    public function reopen(AccountingPeriod $period, User $by, string $reason): AccountingPeriod
    {
        if (! $period->is_closed) {
            throw ValidationException::withMessages(['period' => ['Période déjà ouverte.']]);
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['reason' => ['Raison minimum 10 caractères.']]);
        }
        $period->update([
            'is_closed' => false,
            'metadata' => array_merge((array) $period->metadata, [
                'reopened_at' => now()->toIso8601String(),
                'reopened_by' => $by->id,
                'reopen_reason' => $reason,
                'previous_closed_at' => $period->closed_at?->toIso8601String(),
            ]),
        ]);

        \App\Support\Audit\CriticalActionAuditor::record(
            eventType: 'accounting.period_reopened',
            context: [
                'period_year' => $period->period_year,
                'period_month' => $period->period_month,
                'reason' => $reason,
                'previous_closed_at' => $period->getOriginal('closed_at'),
            ],
            subject: $period,
            actor: $by,
            severity: 'warning',
        );

        return $period->fresh();
    }
}

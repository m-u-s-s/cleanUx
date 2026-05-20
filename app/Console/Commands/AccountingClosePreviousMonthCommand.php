<?php

namespace App\Console\Commands;

use App\Models\AccountingPeriod;
use App\Services\AccountingV2\PeriodCloser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AccountingClosePreviousMonthCommand extends Command
{
    protected $signature = 'accounting:close-previous-month
        {--grace-days=5 : Days into the current month before auto-closing the previous one}
        {--force : Close even if grace period is not respected}';

    protected $description = 'Auto-close the previous accounting period if balanced and outside grace window';

    public function handle(PeriodCloser $closer): int
    {
        if (! config('accounting_v2.enabled', true)) {
            $this->warn('Accounting v2 disabled. Skip.');
            return self::SUCCESS;
        }
        if (! Schema::hasTable('accounting_entries')) {
            $this->warn('accounting_entries table missing. Skip.');
            return self::SUCCESS;
        }

        $grace = (int) $this->option('grace-days');
        $force = (bool) $this->option('force');

        $today = now();
        if (! $force && $today->day < $grace) {
            $this->info(sprintf(
                'Today is day %d of month, grace=%d. Skipping (use --force to override).',
                $today->day, $grace
            ));
            return self::SUCCESS;
        }

        $target = $today->copy()->subMonthNoOverflow()->startOfMonth();
        $year = (int) $target->year;
        $month = (int) $target->month;

        $existing = AccountingPeriod::query()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->first();
        if ($existing && $existing->is_closed) {
            $this->info(sprintf('Period %04d-%02d already closed (at %s).', $year, $month, $existing->closed_at?->toIso8601String()));
            return self::SUCCESS;
        }

        try {
            $period = $closer->close($year, $month);
            $this->info(sprintf(
                'Closed period %04d-%02d: %d entries, debit=%d credit=%d.',
                $year, $month, $period->entry_count, $period->total_debit_cents, $period->total_credit_cents
            ));
            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('[accounting:close-previous-month] failed', [
                'year' => $year, 'month' => $month, 'error' => $e->getMessage(),
            ]);
            $this->error(sprintf('Failed to close %04d-%02d: %s', $year, $month, $e->getMessage()));
            return self::FAILURE;
        }
    }
}

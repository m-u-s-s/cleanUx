<?php

namespace App\Console\Commands;

use App\Services\Ops\SpineHealthReport;
use Illuminate\Console\Command;

/** Go-live drill D1 — rapport de santé du "spine" (DB, cache, queue, Stripe, Reverb, …). */
class SpineHealthReportCommand extends Command
{
    protected $signature = 'spine:health-report';

    protected $description = 'Report the spine health checks (DB, cache, queue, Stripe, Reverb)';

    public function handle(SpineHealthReport $report): int
    {
        $checks = $report->collect();
        $failures = 0;

        foreach ($checks as $name => $check) {
            $ok = is_array($check) ? (bool) ($check['ok'] ?? false) : (bool) $check;
            $detail = is_array($check) ? (string) ($check['detail'] ?? '') : '';

            if (! $ok) {
                $failures++;
            }

            $this->line(sprintf('  [%s] %-22s %s', $ok ? 'OK  ' : 'FAIL', $name, $detail));
        }

        $this->line('');
        $this->info(sprintf('Spine health: %d checks, %d failing.', count($checks), $failures));

        return self::SUCCESS;
    }
}

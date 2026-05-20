<?php

namespace App\Console\Commands;

use App\Models\SubscriptionsV2\SubscriptionV2;
use App\Services\SubscriptionsV2\SubscriptionEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SubscriptionsTickCommand extends Command
{
    protected $signature = 'subscriptions:tick
        {--dry-run : List due subscriptions without ticking them}
        {--limit=200 : Max subscriptions to process this run}';

    protected $description = 'Process due subscription billing cycles (active/trialing/past_due with next_billing_at <= now)';

    public function handle(SubscriptionEngine $engine): int
    {
        if (! config('subscriptions_v2.enabled', true)) {
            $this->warn('Subscriptions v2 is disabled (config subscriptions_v2.enabled=false). Skipping.');
            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        $due = SubscriptionV2::query()
            ->dueForBilling()
            ->limit($limit)
            ->get();

        $this->info(sprintf('Found %d due subscription(s)%s.', $due->count(), $dryRun ? ' (dry-run)' : ''));

        $processed = 0;
        $errors = 0;
        foreach ($due as $sub) {
            if ($dryRun) {
                $this->line(sprintf('  - sub#%d %s (next_billing_at=%s)', $sub->id, $sub->code, $sub->next_billing_at?->toIso8601String() ?? 'n/a'));
                continue;
            }
            try {
                $engine->tick($sub);
                $processed++;
            } catch (\Throwable $e) {
                $errors++;
                Log::error('[subscriptions:tick] failed for subscription', [
                    'subscription_id' => $sub->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error(sprintf('  ✗ sub#%d failed: %s', $sub->id, $e->getMessage()));
            }
        }

        $this->info(sprintf('Tick complete: %d processed, %d errors.', $processed, $errors));
        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}

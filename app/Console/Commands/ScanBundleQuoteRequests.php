<?php

namespace App\Console\Commands;

use App\Services\Bundles\MultiTradeBundleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Bundle Marketplace P4 — cron : expire les demandes de devis dépassées et
 * ré-escalade les items de chantier sous-quotés en sollicitant de nouveaux
 * prestataires.
 */
class ScanBundleQuoteRequests extends Command
{
    protected $signature = 'bundles:scan-quote-requests';

    protected $description = 'Expire stale bundle quote requests and re-escalate under-quoted items';

    public function handle(MultiTradeBundleService $service): int
    {
        if (! Schema::hasTable('multi_trade_bundle_item_quotes')) {
            $this->warn('multi_trade_bundle_item_quotes table missing. Skip.');

            return self::SUCCESS;
        }

        $expired = $service->expireStaleQuotes();
        $solicited = $service->escalateQuotingBundles();

        $this->info(sprintf('Bundle quotes scan: %d expired, %d re-solicited.', $expired, $solicited));

        return self::SUCCESS;
    }
}

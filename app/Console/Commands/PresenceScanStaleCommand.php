<?php

namespace App\Console\Commands;

use App\Services\Presence\ProviderPresenceService;
use Illuminate\Console\Command;

/**
 * Auto-marque offline les providers actifs sans heartbeat depuis N minutes.
 *
 * À scheduler en prod : toutes les 1-2 minutes.
 *
 *   $schedule->command('presence:scan-stale')->everyTwoMinutes();
 */
class PresenceScanStaleCommand extends Command
{
    protected $signature = 'presence:scan-stale
                            {--threshold=5 : Seuil en minutes avant auto-offline}';

    protected $description = 'Marque offline les providers sans heartbeat depuis N minutes (default: 5).';

    public function handle(ProviderPresenceService $service): int
    {
        $threshold = (int) $this->option('threshold');
        if ($threshold < 1) {
            $threshold = 5;
        }

        $count = $service->scanStale($threshold);

        if ($count > 0) {
            $this->info("✓ {$count} provider(s) auto-marqué(s) offline (heartbeat > {$threshold}min).");
        } else {
            $this->line("✓ Aucun provider stale.");
        }

        return 0;
    }
}

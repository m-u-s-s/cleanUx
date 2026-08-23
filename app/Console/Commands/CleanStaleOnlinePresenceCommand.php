<?php

namespace App\Console\Commands;

use App\Services\Provider\ProviderPresenceService;
use Illuminate\Console\Command;

/** Phase 11 — Auto-offline des prestataires "fantômes". */
class CleanStaleOnlinePresenceCommand extends Command
{
    protected $signature = 'presence:cleanup';

    protected $description = 'Auto-offline les prestataires sans heartbeat récent';

    public function handle(ProviderPresenceService $service): int
    {
        $count = $service->cleanStalePresence();

        if ($count > 0) {
            $this->info("✓ {$count} prestataire(s) basculé(s) en offline (heartbeat trop ancien)");
        } else {
            $this->line('Aucun prestataire fantôme détecté.');
        }

        return self::SUCCESS;
    }
}

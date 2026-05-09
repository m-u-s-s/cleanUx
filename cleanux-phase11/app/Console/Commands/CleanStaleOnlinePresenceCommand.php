<?php

namespace App\Console\Commands;

use App\Services\Provider\ProviderPresenceService;
use Illuminate\Console\Command;

/**
 * Phase 11 — Auto-offline des prestataires "fantômes".
 *
 * Un prestataire qui a fait go-online mais dont l'app a crashé / perdu réseau
 * reste artificiellement online. Cette commande tourne toutes les minutes via
 * le scheduler et bascule en offline tous ceux dont le dernier heartbeat
 * date de plus de 5 minutes.
 *
 * Usage scheduler (app/Console/Kernel.php) :
 *   $schedule->command('presence:cleanup')->everyMinute();
 *
 * Usage manuel :
 *   php artisan presence:cleanup
 */
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

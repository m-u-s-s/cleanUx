<?php

namespace App\Console\Commands;

use App\Models\GoogleCalendarConnection;
use App\Models\Parametre;
use App\Services\Integrations\GoogleCalendarSyncService;
use Illuminate\Console\Command;

class SyncGoogleCalendarCommand extends Command
{
    protected $signature = 'google-calendar:sync {--user_id=} {--future-days=30} {--force}';

    protected $description = 'Synchronise les rendez-vous CleanUx vers Google Calendar pour les connexions actives.';

    public function handle(GoogleCalendarSyncService $syncService): int
    {
        $enabled = Parametre::getValeur('calendar_sync_enabled', '0') === '1'
            && Parametre::getValeur('google_calendar_enabled', '0') === '1';

        if (! $enabled && ! $this->option('force')) {
            $this->warn('La synchronisation Google Calendar est désactivée dans les paramètres.');
            return self::SUCCESS;
        }

        $futureDays = max(1, (int) $this->option('future-days'));
        $query = GoogleCalendarConnection::query()->with('user')->where('sync_enabled', true);

        if ($userId = $this->option('user_id')) {
            $query->where('user_id', (int) $userId);
        }

        $connections = $query->get();

        if ($connections->isEmpty()) {
            $this->info('Aucune connexion Google active à synchroniser.');
            return self::SUCCESS;
        }

        foreach ($connections as $connection) {
            $stats = $syncService->syncFutureRendezVousForUser($connection->user, $futureDays);

            $this->line(sprintf(
                '%s → created:%d updated:%d deleted:%d skipped:%d errors:%d',
                $connection->user->email,
                $stats['created'],
                $stats['updated'],
                $stats['deleted'],
                $stats['skipped'],
                count($stats['errors'])
            ));
        }

        return self::SUCCESS;
    }
}

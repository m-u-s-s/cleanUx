<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

class PruneReadNotifications extends Command
{
    protected $signature = 'app:prune-read-notifications {--days=30 : Supprimer les notifications lues plus anciennes que N jours}';

    protected $description = 'Supprime les anciennes notifications déjà lues';

    public function handle(): int
    {
        $days = max((int) $this->option('days'), 1);

        $deleted = DatabaseNotification::query()
            ->whereNotNull('read_at')
            ->where('read_at', '<=', now()->subDays($days))
            ->delete();

        $this->info("{$deleted} notification(s) lue(s) supprimée(s).");

        return self::SUCCESS;
    }
}

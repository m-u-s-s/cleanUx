<?php

namespace App\Console\Commands\Gdpr;

use App\Services\Gdpr\RetentionPolicyService;
use Illuminate\Console\Command;

class EnforceRetentionCommand extends Command
{
    protected $signature = 'gdpr:enforce-retention';

    protected $description = 'Purge les données dépassant leur durée de rétention RGPD (activity logs, notifications, sessions, failed_jobs)';

    public function handle(RetentionPolicyService $service): int
    {
        $stats = $service->enforceAll();

        $rows = [];
        foreach ($stats as $table => $count) {
            $rows[] = [$table, $count];
        }

        $this->table(['Table', 'Lignes purgées'], $rows);

        $this->info('Total : ' . array_sum($stats) . ' ligne(s) purgée(s).');

        return self::SUCCESS;
    }
}

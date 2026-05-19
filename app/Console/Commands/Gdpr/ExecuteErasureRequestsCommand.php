<?php

namespace App\Console\Commands\Gdpr;

use App\Models\GdprDataRequest;
use App\Services\Gdpr\DataErasureService;
use Illuminate\Console\Command;

class ExecuteErasureRequestsCommand extends Command
{
    protected $signature = 'gdpr:execute-erasures {--dry-run : Affiche sans appliquer}';

    protected $description = 'Exécute les demandes d\'erasure dont la grace period est passée';

    public function handle(DataErasureService $service): int
    {
        $ready = GdprDataRequest::query()->readyForExecution()->get();

        if ($ready->isEmpty()) {
            $this->info('Aucune demande à exécuter.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $executed = 0;
        $failed = 0;

        foreach ($ready as $request) {
            $this->line(sprintf(
                '[%s] user_id=%d (grace_period_ends_at=%s)',
                $request->reference,
                $request->user_id,
                $request->grace_period_ends_at?->format('d/m/Y H:i') ?? '?',
            ));

            if ($dryRun) {
                continue;
            }

            try {
                $service->execute($request);
                $executed++;
            } catch (\Throwable $e) {
                $this->error('  ⚠ Erreur #' . $request->id . ' : ' . $e->getMessage());
                $failed++;
            }
        }

        if ($dryRun) {
            $this->warn(sprintf('[dry-run] %d demande(s) auraient été exécutées.', $ready->count()));
        } else {
            $this->info(sprintf('%d demande(s) exécutées, %d échec(s).', $executed, $failed));
        }

        return self::SUCCESS;
    }
}

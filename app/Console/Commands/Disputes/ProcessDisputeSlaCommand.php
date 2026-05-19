<?php

namespace App\Console\Commands\Disputes;

use App\Services\Disputes\DisputeService;
use App\Services\Disputes\DisputeSlaService;
use Illuminate\Console\Command;

class ProcessDisputeSlaCommand extends Command
{
    protected $signature = 'disputes:process-sla {--dry-run : Affiche sans modifier}';

    protected $description = 'Escalade les disputes dont le SLA est dépassé';

    public function handle(DisputeSlaService $sla, DisputeService $disputes): int
    {
        $overdue = $sla->findOverdueForEscalation();

        if ($overdue->isEmpty()) {
            $this->info('Aucune dispute en retard.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        $escalated = 0;
        foreach ($overdue as $case) {
            $this->line(sprintf(
                '[%s] %s — niveau %d → due_at %s',
                $case->reference,
                $case->status,
                $case->escalation_level,
                optional($case->due_at)->format('d/m/Y H:i') ?? '?',
            ));

            if (! $dryRun) {
                try {
                    $disputes->escalate($case, 'SLA dépassé');
                    $escalated++;
                } catch (\Throwable $e) {
                    $this->error("  ⚠ Erreur escalade #{$case->id}: " . $e->getMessage());
                }
            }
        }

        if ($dryRun) {
            $this->warn(sprintf('[dry-run] %d dispute(s) auraient été escaladées.', $overdue->count()));
        } else {
            $this->info(sprintf('%d dispute(s) escaladées.', $escalated));
        }

        return self::SUCCESS;
    }
}

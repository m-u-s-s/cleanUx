<?php

namespace App\Console\Commands;

use App\Models\Mission;
use App\Support\Domain\MissionStatus;
use Illuminate\Console\Command;

/**
 * Go-live drill D5 — détecte les missions bloquées dans un état non terminal
 * au-delà de leur début planifié. Sort en échec (exit 1) s'il y en a, pour
 * déclencher l'alerting/monitoring.
 */
class CheckStuckMissions extends Command
{
    protected $signature = 'spine:check-stuck-missions {--hours=6 : Ancienneté (h) du planned_start au-delà de laquelle une mission non terminée est considérée bloquée}';

    protected $description = 'Flag missions stuck in a non-terminal state past their planned start (exit 1 if any)';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $cutoff = now()->subHours($hours);

        $stuck = Mission::query()
            ->whereNotIn('status', [MissionStatus::COMPLETED, MissionStatus::CANCELLED])
            ->whereNull('actual_end_at')
            ->whereNotNull('planned_start_at')
            ->where('planned_start_at', '<', $cutoff)
            ->count();

        $this->info("Missions bloquées (> {$hours}h, non terminées) : {$stuck}");

        return $stuck > 0 ? self::FAILURE : self::SUCCESS;
    }
}

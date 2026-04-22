<?php

namespace App\Console\Commands;

use App\Services\Ops\ProductionHealthReport;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;

class GoLiveReadinessReport extends Command
{
    protected $signature = 'app:go-live-readiness-report {--json : Affiche le rapport en JSON} {--strict : Retourne un code d\'erreur si des checks ERROR échouent ou si des commandes scheduler manquent}';

    protected $description = 'Rapport synthétique de readiness avant mise en ligne ou cutover production';

    public function handle(ProductionHealthReport $healthReport, Schedule $schedule): int
    {
        $health = $healthReport->build();
        $expectedCommands = collect(config('operations.deployment.expected_schedule_commands', []))->filter()->values();
        $actualCommands = collect($schedule->events())
            ->map(fn ($event) => trim((string) ($event->command ?? '')))
            ->filter()
            ->values();

        $missingCommands = $expectedCommands
            ->reject(fn ($expected) => $actualCommands->contains(fn ($actual) => str_contains($actual, $expected)))
            ->values();

        $summary = [
            'errors' => $healthReport->errorCount($health),
            'warnings' => $healthReport->warningCount($health),
            'expected_schedule_commands' => $expectedCommands->count(),
            'missing_schedule_commands' => $missingCommands->count(),
            'ready' => $healthReport->errorCount($health) === 0 && $missingCommands->isEmpty(),
        ];

        $payload = [
            'summary' => $summary,
            'health_metrics' => $health['metrics'],
            'missing_schedule_commands' => $missingCommands->all(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(
                ['Metric', 'Value'],
                collect($summary)->map(fn ($value, $key) => [$key, is_bool($value) ? ($value ? 'true' : 'false') : (string) $value])->all()
            );

            if ($missingCommands->isNotEmpty()) {
                $this->newLine();
                $this->warn('Commandes scheduler attendues mais absentes :');
                foreach ($missingCommands as $command) {
                    $this->line('- ' . $command);
                }
            }

            $this->newLine();
            $this->table(
                ['Metric', 'Value'],
                collect($health['metrics'])->map(fn ($value, $key) => [$key, is_bool($value) ? ($value ? 'true' : 'false') : (string) ($value ?? 'null')])->all()
            );
        }

        if ($summary['ready']) {
            $this->info('Go-live readiness report OK.');
            return self::SUCCESS;
        }

        $this->warn('Go-live readiness report détecte des points bloquants ou incomplets.');

        return $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }
}

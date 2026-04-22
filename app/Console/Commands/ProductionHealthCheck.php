<?php

namespace App\Console\Commands;

use App\Services\Ops\ProductionHealthReport;
use Illuminate\Console\Command;

class ProductionHealthCheck extends Command
{
    protected $signature = 'app:production-health-check {--strict : Retourne un code d\'erreur si des checks ERROR échouent}';

    protected $description = 'Contrôle de préparation production: queue, storage, mail, monitoring et backups';

    public function handle(ProductionHealthReport $reporter): int
    {
        $report = $reporter->build();

        $this->table(
            ['Statut', 'Sévérité', 'Check', 'Valeur'],
            collect($report['checks'])->map(fn ($check) => [
                $check['status'],
                $check['severity'],
                $check['label'],
                (string) ($check['value'] ?? ''),
            ])->all()
        );

        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            collect($report['metrics'])->map(fn ($value, $key) => [$key, is_bool($value) ? ($value ? 'true' : 'false') : (string) $value])->all()
        );

        $errors = $reporter->errorCount($report);

        if ($errors === 0) {
            $this->info('Production health check OK.');
            return self::SUCCESS;
        }

        $this->warn('Production health check détecte des erreurs bloquantes.');

        return $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }
}

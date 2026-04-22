<?php

namespace App\Console\Commands;

use App\Support\Platform\PlatformReadinessReport;
use Illuminate\Console\Command;

class AuditPlatformIntegrity extends Command
{
    protected $signature = 'app:audit-platform-integrity {--fail-on-issues : Retourne un code erreur si des anomalies bloquantes sont détectées}';

    protected $description = 'Audit rapide des incohérences métier principales de la plateforme';

    public function handle(PlatformReadinessReport $reporter): int
    {
        $report = $reporter->build();

        $this->components->twoColumnDetail('Profil seed', $report['profile']);
        $this->components->twoColumnDetail('Référentiel prêt', $report['summary']['reference_ready'] ? 'OK' : 'À corriger');
        $this->components->twoColumnDetail('Readiness seed', $report['summary']['seed_ready'] ? 'OK' : 'À corriger');
        $this->components->twoColumnDetail('Erreurs bloquantes', (string) $report['summary']['errors']);
        $this->components->twoColumnDetail('Warnings', (string) $report['summary']['warnings']);
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            collect($report['metrics'])->map(fn ($value, $key) => [$key, $value])->values()->all()
        );

        $this->newLine();

        $this->table(
            ['Statut', 'Sévérité', 'Check', 'Count'],
            collect($report['checks'])->map(fn (array $check) => [
                strtoupper($check['status']),
                strtoupper($check['severity']),
                $check['label'],
                $check['count'],
            ])->all()
        );

        if ($report['summary']['seed_ready']) {
            $this->info('Audit OK : aucune anomalie bloquante détectée.');

            return self::SUCCESS;
        }

        $this->warn('Audit terminé avec incohérences détectées.');

        return $this->option('fail-on-issues') ? self::FAILURE : self::SUCCESS;
    }
}

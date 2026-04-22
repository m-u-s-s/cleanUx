<?php

namespace App\Console\Commands;

use App\Support\Platform\PlatformReadinessReport;
use Illuminate\Console\Command;

class PrepareFreshSeed extends Command
{
    protected $signature = 'app:prepare-fresh-seed {--strict : Retourne un code erreur si le seed n\'est pas prêt}';

    protected $description = 'Checklist finale après migrate:fresh --seed pour valider un état propre et exploitable';

    public function handle(PlatformReadinessReport $reporter): int
    {
        $report = $reporter->build();

        $this->components->info('Checklist post-seed CleanUx');
        $this->newLine();

        $this->components->twoColumnDetail('Profil seed', $report['profile']);
        $this->components->twoColumnDetail('Référentiel prêt', $report['summary']['reference_ready'] ? 'oui' : 'non');
        $this->newLine();

        $this->table(
            ['Bloc', 'Valeur'],
            [
                ['Pays', $report['metrics']['countries_total']],
                ['Codes postaux', $report['metrics']['postal_codes_total']],
                ['Zones de service', $report['metrics']['service_zones_total']],
                ['Règles zone/service', $report['metrics']['zone_rules_total']],
                ['Catalogues services', $report['metrics']['service_catalogs_total']],
                ['Utilisateurs', $report['metrics']['users_total']],
                ['Employés', $report['metrics']['employees_total']],
                ['Clients / entreprises', $report['metrics']['clients_total']],
                ['Comptes entreprise', $report['metrics']['organization_accounts_total']],
                ['Sites entreprise', $report['metrics']['organization_sites_total']],
                ['Affectations employé-zone', $report['metrics']['employee_zone_assignments_total']],
                ['Rendez-vous', $report['metrics']['rendezvous_total']],
                ['Feedbacks', $report['metrics']['feedbacks_total']],
            ]
        );

        $this->newLine();

        $this->table(
            ['Résultat', 'Sévérité', 'Contrôle', 'Count'],
            collect($report['checks'])->map(fn (array $check) => [
                strtoupper($check['status']),
                strtoupper($check['severity']),
                $check['label'],
                $check['count'],
            ])->all()
        );

        $blockingIssues = $report['summary']['blocking_issues'];

        $this->newLine();
        $this->components->twoColumnDetail('Seed prêt', $report['summary']['seed_ready'] ? 'oui' : 'non');
        $this->components->twoColumnDetail('Checks bloquants', (string) $blockingIssues);
        $this->components->twoColumnDetail('Checks non bloquants', (string) $report['summary']['non_blocking_issues']);

        if (! $report['summary']['seed_ready']) {
            $this->warn('Le projet doit encore être nettoyé avant de considérer le seed comme totalement fiable.');

            return $this->option('strict') ? self::FAILURE : self::SUCCESS;
        }

        $this->info('La base seedée est cohérente et prête pour les validations finales.');

        return self::SUCCESS;
    }
}

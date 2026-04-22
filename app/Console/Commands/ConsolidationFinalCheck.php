<?php

namespace App\Console\Commands;

use App\Support\Platform\LegacyLiteralReport;
use Illuminate\Console\Command;

class ConsolidationFinalCheck extends Command
{
    protected $signature = 'app:consolidation-final-check {--strict : Retourne un code erreur si des flags restent présents}';

    protected $description = 'Scan final de consolidation: littéraux legacy, TODO/FIXME et points de nettoyage restants';

    public function handle(LegacyLiteralReport $reporter): int
    {
        $report = $reporter->build();

        $this->components->twoColumnDetail('Total flags', (string) $report['summary']['total_flags']);
        $this->components->twoColumnDetail('Catégories impactées', (string) $report['summary']['categories_with_flags']);
        $this->newLine();

        $this->table(
            ['Catégorie', 'Occurrences', 'Exemples de fichiers'],
            collect($report['checks'])->map(function (array $check) {
                $examples = collect($check['files'])
                    ->map(fn (array $file) => $file['file'] . ' (' . $file['count'] . ')')
                    ->implode("\n");

                return [
                    $check['label'],
                    (string) $check['count'],
                    $examples,
                ];
            })->all()
        );

        if (($report['summary']['total_flags'] ?? 0) === 0) {
            $this->info('Consolidation finale OK : aucun littéral legacy détecté.');
            return self::SUCCESS;
        }

        $this->warn('Consolidation finale : des points de nettoyage restent visibles.');

        return $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands\I18n;

use App\Services\I18n\TranslationScanner;
use Illuminate\Console\Command;

class ScanTranslationsCommand extends Command
{
    protected $signature = 'translations:scan {--locale= : Locale spécifique} {--show-keys : Affiche les clés manquantes}';

    protected $description = 'Détecte les clés de traduction manquantes ou non-traduites par locale';

    public function handle(TranslationScanner $scanner): int
    {
        $report = $scanner->scanAllLocales();
        $locale = $this->option('locale');

        if ($locale) {
            if (! isset($report[$locale])) {
                $this->error("Locale {$locale} non trouvée.");
                return self::FAILURE;
            }
            $report = [$locale => $report[$locale]];
        }

        $rows = [];
        foreach ($report as $loc => $data) {
            $rows[] = [
                $loc,
                $data['total'],
                count($data['missing']),
                count($data['untranslated']),
            ];
        }

        $this->table(['Locale', 'Total', 'Manquantes', 'Non-traduites'], $rows);

        if ($this->option('show-keys')) {
            foreach ($report as $loc => $data) {
                if (! empty($data['missing'])) {
                    $this->newLine();
                    $this->warn("[{$loc}] Clés manquantes :");
                    foreach (array_slice($data['missing'], 0, 30) as $k) {
                        $this->line('  - ' . $k);
                    }
                    if (count($data['missing']) > 30) {
                        $this->line('  … et ' . (count($data['missing']) - 30) . ' autres');
                    }
                }
            }
        }

        return self::SUCCESS;
    }
}

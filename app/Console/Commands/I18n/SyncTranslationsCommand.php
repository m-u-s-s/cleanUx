<?php

namespace App\Console\Commands\I18n;

use App\Services\I18n\LocaleResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SyncTranslationsCommand extends Command
{
    protected $signature = 'translations:sync {--source=en : Locale source} {--target= : Locales cibles (csv) — par défaut toutes sauf source} {--force : Écrase fichiers existants}';

    protected $description = 'Crée les fichiers de langue manquants en copiant depuis la source (placeholders pour traduction)';

    public function handle(LocaleResolver $resolver): int
    {
        $source = (string) $this->option('source');
        $sourceDir = base_path("lang/{$source}");

        if (! is_dir($sourceDir)) {
            $this->error("Source lang/{$source} introuvable.");
            return self::FAILURE;
        }

        $targets = $this->option('target')
            ? array_map('trim', explode(',', (string) $this->option('target')))
            : array_diff($resolver->supportedCodes(), [$source]);

        $force = (bool) $this->option('force');
        $copied = 0;
        $skipped = 0;

        foreach ($targets as $target) {
            if (! $resolver->isSupported($target)) {
                $this->warn("Locale cible {$target} non supportée — skipped");
                continue;
            }

            $targetDir = base_path("lang/{$target}");
            if (! is_dir($targetDir)) {
                File::makeDirectory($targetDir, 0755, true);
            }

            foreach (glob($sourceDir . '/*.php') as $file) {
                $name = basename($file);
                $dest = $targetDir . '/' . $name;

                if (file_exists($dest) && ! $force) {
                    $skipped++;
                    continue;
                }

                copy($file, $dest);
                $copied++;
            }

            $sourceJson = base_path("lang/{$source}.json");
            $targetJson = base_path("lang/{$target}.json");
            if (file_exists($sourceJson) && (! file_exists($targetJson) || $force)) {
                copy($sourceJson, $targetJson);
                $copied++;
            }
        }

        $this->info("Fichiers copiés: {$copied}, skipped: {$skipped}.");
        $this->line("Note: les fichiers sont des copies textuelles de '{$source}' — il faut maintenant les traduire.");

        return self::SUCCESS;
    }
}

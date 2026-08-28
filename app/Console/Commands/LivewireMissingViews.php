<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class LivewireMissingViews extends Command
{
    protected $signature = 'livewire:missing-views';

    protected $description = 'Liste les composants Livewire sans fichier blade associé';

    public function handle(): int
    {
        $componentDir = app_path('Livewire');
        $viewDir = resource_path('views/livewire');

        if (! File::isDirectory($componentDir)) {
            $this->error("Le dossier app/Livewire n'existe pas.");

            return self::FAILURE;
        }

        $missing = [];

        foreach (File::allFiles($componentDir) as $file) {
            // `app/Livewire/Concerns` porte du code partage : un trait ne rend aucune vue.
            if (! $this->estUnComposant($file->getPathname())) {
                continue;
            }

            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $segments = explode('/', str_replace('.php', '', $relative));
            $conventionalViewRelative = collect($segments)
                ->map(fn (string $segment) => Str::kebab($segment))
                ->implode('/');

            $declaredViews = $this->extractDeclaredViews($file->getPathname());

            if ($declaredViews !== []) {
                $resolvedViews = collect($declaredViews)
                    ->map(fn (string $view) => resource_path('views/'.str_replace('.', '/', $view).'.blade.php'));

                if ($resolvedViews->contains(fn (string $path) => File::exists($path))) {
                    continue;
                }

                $missing[] = [
                    'component' => 'App\\Livewire\\'.str_replace('/', '\\', str_replace('.php', '', $relative)),
                    'view' => $resolvedViews->implode(' | '),
                ];

                continue;
            }

            $conventionalViewFile = $viewDir.'/'.$conventionalViewRelative.'.blade.php';

            if (! File::exists($conventionalViewFile)) {
                $missing[] = [
                    'component' => 'App\\Livewire\\'.str_replace('/', '\\', str_replace('.php', '', $relative)),
                    'view' => 'resources/views/livewire/'.$conventionalViewRelative.'.blade.php',
                ];
            }
        }

        if (empty($missing)) {
            $this->info('✅ Tous les composants ont leur fichier blade.');

            return self::SUCCESS;
        }

        $this->warn('❗ Composants sans blade associé :');
        foreach ($missing as $row) {
            $this->line("• {$row['component']} → {$row['view']}");
        }

        return self::FAILURE;
    }

    /** Un trait, une interface, un enum ou une classe abstraite n'est pas un composant. */
    protected function estUnComposant(string $path): bool
    {
        $source = File::get($path);

        if (preg_match('/^\s*(trait|interface|enum)\s+\w/m', $source) === 1) {
            return false;
        }

        return preg_match('/^\s*abstract\s+class\s+\w/m', $source) !== 1;
    }

    /**
     * @return array<int, string>
     */
    protected function extractDeclaredViews(string $path): array
    {
        $content = File::get($path);

        preg_match_all("/view\\(\\s*['\"]([^'\"]+)['\"]/", $content, $matches);

        return array_values(array_unique(array_filter($matches[1] ?? [])));
    }
}

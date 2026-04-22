<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Component;

class LivewireUnusedInViews extends Command
{
    protected $signature = 'livewire:unused-includes';
    protected $description = 'Liste les composants Livewire jamais appelés dans des vues, en excluant les composants full-page utilisés dans les routes';

    public function handle(): int
    {
        $componentDir = app_path('Livewire');
        $viewFiles = File::allFiles(resource_path('views'));

        $aliasesUsed = [];
        foreach ($viewFiles as $view) {
            $content = File::get($view->getPathname());

            preg_match_all('/@livewire\(\s*["\']([a-zA-Z0-9_\.\-\/]+)["\']/', $content, $classic);
            preg_match_all('/<livewire:([a-zA-Z0-9_\.\-\/]+)(?:\s|\/?>)/', $content, $tagged);

            $aliasesUsed = array_merge($aliasesUsed, $classic[1] ?? [], $tagged[1] ?? []);
        }

        $aliasesUsed = array_values(array_unique(array_map(function (string $alias) {
            return str_replace('.', '/', trim($alias));
        }, $aliasesUsed)));

        $routedClasses = [];
        foreach (Route::getRoutes() as $route) {
            $action = $route->getAction();

            foreach (['controller', 'uses'] as $key) {
                $value = $action[$key] ?? null;
                if (! is_string($value)) {
                    continue;
                }

                $class = str_contains($value, '@') ? explode('@', $value)[0] : $value;
                if (class_exists($class) && is_subclass_of($class, Component::class)) {
                    $routedClasses[] = $class;
                }
            }
        }
        $routedClasses = array_unique($routedClasses);

        $unused = [];
        foreach (File::allFiles($componentDir) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $class = 'App\\Livewire\\'.str_replace(['/', '.php'], ['\\', ''], $relative);

            if (! class_exists($class) || ! is_subclass_of($class, Component::class)) {
                continue;
            }

            if (in_array($class, $routedClasses, true)) {
                continue;
            }

            $segments = explode('/', str_replace('.php', '', $relative));
            $alias = collect($segments)
                ->map(fn (string $segment) => Str::kebab($segment))
                ->implode('/');

            if (! in_array($alias, $aliasesUsed, true)) {
                $unused[] = [
                    'component' => $class,
                    'alias' => $alias,
                ];
            }
        }

        if (empty($unused)) {
            $this->info('✅ Aucun composant non inclus détecté hors composants routés.');
            return self::SUCCESS;
        }

        $this->warn('❗ Composants Livewire non inclus dans les vues :');
        foreach ($unused as $row) {
            $this->line("• {$row['component']} (alias: {$row['alias']})");
        }

        return self::SUCCESS;
    }
}

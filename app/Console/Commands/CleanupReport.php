<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

class CleanupReport extends Command
{
    protected $signature = 'app:cleanup-report';
    protected $description = 'Rapport rapide de nettoyage du projet';

    public function handle(): int
    {
        $components = $this->discoverLivewireComponents();
        $usedInRoutes = $this->discoverRouteLinkedComponents($components);
        $livewireViews = $this->discoverLivewireViews();
        $includedAliases = $this->discoverIncludedAliases();

        $this->componentsReport($components, $usedInRoutes, $includedAliases);
        $this->viewsReport($livewireViews);
        $this->routesReport();

        $this->newLine();
        $this->info('Rapport terminé.');

        return self::SUCCESS;
    }

    protected function discoverLivewireComponents(): array
    {
        $componentDir = app_path('Livewire');
        $allComponents = [];

        foreach (File::allFiles($componentDir) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $class = 'App\\Livewire\\' . str_replace(['/', '.php'], ['\\', ''], $relative);

            if (class_exists($class) && is_subclass_of($class, Component::class)) {
                $allComponents[] = $class;
            }
        }

        sort($allComponents);

        return $allComponents;
    }

    protected function discoverRouteLinkedComponents(array $components): array
    {
        $used = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getAction();

            foreach (['controller', 'uses'] as $key) {
                $value = $action[$key] ?? null;

                if (! is_string($value)) {
                    continue;
                }

                $class = str_contains($value, '@') ? explode('@', $value)[0] : $value;

                if (in_array($class, $components, true)) {
                    $used[] = $class;
                }
            }
        }

        $used = array_values(array_unique($used));
        sort($used);

        return $used;
    }

    protected function discoverLivewireViews(): array
    {
        return collect(File::allFiles(resource_path('views/livewire')))
            ->map(function ($file) {
                return str_replace('\\', '/', $file->getRelativePathname());
            })
            ->filter(fn (string $path) => str_ends_with($path, '.blade.php'))
            ->values()
            ->all();
    }

    protected function discoverIncludedAliases(): array
    {
        $aliases = [];

        foreach (File::allFiles(resource_path('views')) as $view) {
            $content = File::get($view->getPathname());

            preg_match_all('/@livewire\(\s*["\']([a-zA-Z0-9_\.\-\/]+)["\']/', $content, $classic);
            preg_match_all('/<livewire:([a-zA-Z0-9_\.\-\/]+)(?:\s|\/?>)/', $content, $tagged);

            $aliases = array_merge($aliases, $classic[1] ?? [], $tagged[1] ?? []);
        }

        $aliases = array_values(array_unique(array_map(fn (string $alias) => str_replace('.', '/', trim($alias)), $aliases)));
        sort($aliases);

        return $aliases;
    }

    protected function componentAlias(string $class): string
    {
        $relative = str_replace('App\\Livewire\\', '', $class);
        $segments = explode('\\', $relative);

        $segments = array_map(function (string $segment) {
            return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $segment));
        }, $segments);

        return implode('/', $segments);
    }

    protected function componentsReport(array $components, array $usedInRoutes, array $includedAliases): void
    {
        $orphans = [];

        foreach ($components as $class) {
            $alias = $this->componentAlias($class);

            if (! in_array($class, $usedInRoutes, true) && ! in_array($alias, $includedAliases, true)) {
                $orphans[] = $class;
            }
        }

        $this->line('=== Livewire ===');
        $this->line('Total composants : ' . count($components));
        $this->line('Utilisés dans les routes : ' . count($usedInRoutes));
        $this->line('Possiblement orphelins : ' . count($orphans));
        $this->newLine();
    }

    protected function viewsReport(array $livewireViews): void
    {
        $views = collect(File::allFiles(resource_path('views')))
            ->map(fn ($file) => str_replace('\\', '/', $file->getRelativePathname()))
            ->filter(fn ($path) => str_ends_with($path, '.blade.php'));

        $this->line('=== Vues ===');
        $this->line('Total vues Blade : ' . $views->count());
        $this->line('Vues Livewire : ' . count($livewireViews));
        $this->newLine();
    }

    protected function routesReport(): void
    {
        $routes = collect(Route::getRoutes());

        $this->line('=== Routes ===');
        $this->line('Total routes : ' . $routes->count());
        $this->line('Routes GET : ' . $routes->filter(fn ($route) => in_array('GET', $route->methods(), true))->count());
        $this->line('Routes POST : ' . $routes->filter(fn ($route) => in_array('POST', $route->methods(), true))->count());
        $this->newLine();
    }
}

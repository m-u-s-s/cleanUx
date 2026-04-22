<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

class FindUnusedLivewireComponents extends Command
{
    protected $signature = 'livewire:unused';
    protected $description = 'Liste les composants Livewire qui ne sont utilisés dans aucune route';

    public function handle(): int
    {
        $componentDir = app_path('Livewire');

        if (! File::isDirectory($componentDir)) {
            $this->error("❌ Le dossier app/Livewire n'existe pas.");
            return self::FAILURE;
        }

        $declaredComponents = [];

        foreach (File::allFiles($componentDir) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $class = 'App\\Livewire\\'.str_replace(['/', '.php'], ['\\', ''], $relative);

            if (class_exists($class) && is_subclass_of($class, Component::class)) {
                $declaredComponents[] = $class;
            }
        }

        $usedComponents = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getAction();

            foreach (['controller', 'uses'] as $key) {
                $value = $action[$key] ?? null;
                if (! is_string($value)) {
                    continue;
                }

                $class = str_contains($value, '@') ? explode('@', $value)[0] : $value;

                if (in_array($class, $declaredComponents, true)) {
                    $usedComponents[] = $class;
                }
            }
        }

        $usedComponents = array_values(array_unique($usedComponents));
        $unused = array_values(array_diff($declaredComponents, $usedComponents));

        $this->table(['Check', 'Count'], [
            ['Check' => 'Total composants', 'Count' => count($declaredComponents)],
            ['Check' => 'Utilisés dans les routes', 'Count' => count($usedComponents)],
            ['Check' => 'Possiblement orphelins', 'Count' => count($unused)],
        ]);

        if (empty($unused)) {
            $this->info('✅ Aucun composant inutilisé trouvé.');
            return self::SUCCESS;
        }

        $this->warn('❗ Composants Livewire non utilisés dans les routes :');
        foreach ($unused as $comp) {
            $this->line("• {$comp}");
        }

        return self::SUCCESS;
    }
}

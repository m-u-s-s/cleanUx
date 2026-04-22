<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

class ListLivewireRoutes extends Command
{
    protected $signature = 'livewire:routes';

    protected $description = 'Liste toutes les routes associées à des composants Livewire';

    public function handle()
    {
        $routes = Route::getRoutes();
        $livewireRoutes = [];

        foreach ($routes as $route) {
            $action = $route->getAction('uses');

            if (is_string($action) && is_subclass_of($action, \Livewire\Component::class)) {
                $livewireRoutes[] = [
                    'uri' => $route->uri(),
                    'name' => $route->getName() ?? '—',
                    'method' => implode('|', $route->methods()),
                    'component' => $action,
                ];
            }
        }

        if (empty($livewireRoutes)) {
            $this->warn("❌ Aucune route Livewire trouvée.");
            return;
        }

        $this->table(['Méthode', 'URI', 'Nom', 'Composant'], $livewireRoutes);
        $this->info("✅ " . count($livewireRoutes) . " route(s) Livewire détectée(s).");
    }
}

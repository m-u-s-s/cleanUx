<?php

namespace App\Providers;

use App\Admin\Console\ResourceRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Enregistre les descripteurs de la console d'administration.
 *
 * C'est le SEUL endroit où l'on déclare qu'un domaine est servi par le moteur. Ajouter un
 * descripteur ici sans basculer `coverage` sur `descriptor` dans `config/admin_console.php` — ou
 * l'inverse — fait échouer `ResourceRegistryTest`. Les deux gestes vont ensemble, délibérément :
 * c'est ce qui empêche l'annuaire d'annoncer un module que rien ne sait rendre.
 */
class AdminConsoleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton : chaque instanciation reconstruirait tous les descripteurs, et une liste
        // d'annuaire en ferait autant de fois qu'elle a de lignes.
        $this->app->singleton(ResourceRegistry::class, function ($app) {
            $registry = new ResourceRegistry($app);

            // Un appel par domaine servi. La clé doit exister dans `config/admin_console.php` ET
            // y porter `coverage => 'descriptor'` : ResourceRegistryTest refuse les deux écarts.
            // (Renseigné lot par lot — sous-projet B, tâches 5 à 7.)

            return $registry;
        });
    }
}

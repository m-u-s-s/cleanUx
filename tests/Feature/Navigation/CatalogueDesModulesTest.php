<?php

namespace Tests\Feature\Navigation;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * LE CATALOGUE DOIT COUVRIR CE QUI EXISTE, PAS CE QU'ON CROIT AVOIR ÉCRIT.
 *
 * Ce test part de la table de routes réelle — la seule source qui ne peut pas mentir sur ce qui
 * existe. Un test qui lirait `config/modules.php` pour vérifier `config/modules.php` ne prouverait
 * rien : c'est exactement le piège dans lequel les tests de joignabilité de ce dépôt sont déjà
 * tombés, en asserant qu'une route était DÉCLARÉE plutôt qu'ATTEIGNABLE.
 */
class CatalogueDesModulesTest extends TestCase
{
    /** Les pages de tableau de bord réellement servies, par nom de route. */
    private function pagesDeTableauDeBord(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('GET', $route->methods(), true))
            ->filter(fn ($route) => preg_match('#^(admin|dashboard)(/|$)#', $route->uri()) === 1)
            ->reject(fn ($route) => str_contains($route->uri(), '{'))
            ->map(fn ($route) => $route->getName())
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function test_chaque_page_de_tableau_de_bord_a_sa_case_ou_sa_raison(): void
    {
        $avecCase = collect(config('modules.catalogue'))->pluck('route')->all();
        $sansCase = array_keys(config('modules.non_modules'));

        $orphelines = collect($this->pagesDeTableauDeBord())
            ->reject(fn ($nom) => in_array($nom, $avecCase, true))
            ->reject(fn ($nom) => in_array($nom, $sansCase, true))
            ->values()
            ->all();

        $this->assertSame([], $orphelines, sprintf(
            "%d page(s) de tableau de bord sans case dans config/modules.php :\n  %s\n\n".
            'Ajouter une entrée au catalogue, ou une ligne dans non_modules avec sa raison.',
            count($orphelines),
            implode("\n  ", $orphelines),
        ));
    }

    public function test_aucune_case_ne_mene_a_une_route_inexistante(): void
    {
        // Une case morte est pire qu'une case absente : elle promet une page et rend un 404.
        $mortes = collect(config('modules.catalogue'))
            ->reject(fn ($module) => Route::has($module['route']))
            ->pluck('route')
            ->values()
            ->all();

        $this->assertSame([], $mortes, 'Cases pointant vers une route inexistante : '.implode(', ', $mortes));
    }

    public function test_chaque_case_declare_un_contexte_et_une_categorie_connus(): void
    {
        /*
         * `*` est un contexte à part entière : celui des modules TRANSVERSAUX — profil,
         * notifications, aide, textes légaux. Ces pages ne vivent sous aucun tableau de bord et
         * appartiennent aux cinq espaces à la fois ; les recopier cinq fois serait cinq occasions
         * d'en oublier une.
         */
        $contextes = ['*', 'client', 'employe', 'admin', 'client-company', 'provider-company'];
        $categories = array_keys(config('modules.categories'));

        foreach (config('modules.catalogue') as $module) {
            $this->assertContains($module['context'], $contextes, "Contexte inconnu pour {$module['key']}");
            $this->assertContains($module['category'], $categories, "Catégorie inconnue pour {$module['key']}");
        }
    }

    public function test_aucune_cle_de_case_en_double(): void
    {
        $cles = collect(config('modules.catalogue'))->pluck('key');

        $this->assertSame($cles->unique()->count(), $cles->count(), 'Clés dupliquées dans le catalogue');
    }
}

<?php

namespace Tests\Feature\Navigation;

use App\Support\Navigation\ModuleCatalogue;
use App\Support\Navigation\ModuleIcons;
use Tests\TestCase;

class ModuleCatalogueTest extends TestCase
{
    public function test_groupe_les_modules_d_un_contexte_par_categorie(): void
    {
        $groupes = ModuleCatalogue::pourContexte('client');

        $this->assertNotEmpty($groupes);
        foreach ($groupes as $groupe) {
            $this->assertArrayHasKey('category', $groupe);
            $this->assertArrayHasKey('label', $groupe);
            $this->assertNotEmpty($groupe['modules'], 'Une catégorie vide ne doit pas être rendue');
            foreach ($groupe['modules'] as $module) {
                // `*` = module transversal, servi à tous les contextes : profil, notifications,
                // aide, textes légaux. Il appartient donc légitimement à celui-ci.
                $this->assertContains($module['context'], ['client', '*'], $module['key']);
            }
        }
    }

    public function test_retire_les_modules_dont_la_route_n_existe_pas(): void
    {
        // Une case morte promet une page et rend un 404. `Route::has` est le seul juge : les
        // routes varient selon les modules activés.
        config()->set('modules.catalogue', [
            ['key' => 'client:fantome', 'label' => 'Fantôme', 'icon' => '👻', 'route' => 'route.qui.nexiste.pas',
                'context' => 'client', 'category' => 'comptes', 'primary' => false],
        ]);

        $this->assertCount(0, ModuleCatalogue::pourContexte('client'));
    }

    public function test_respecte_l_ordre_des_categories_du_registre(): void
    {
        $attendu = array_keys(config('modules.categories'));
        $obtenu = ModuleCatalogue::pourContexte('admin')->pluck('category')->all();

        $this->assertSame(array_values(array_intersect($attendu, $obtenu)), $obtenu);
    }

    public function test_ne_rend_que_cinq_principaux_au_maximum(): void
    {
        foreach (['client', 'employe', 'admin', 'client-company', 'provider-company'] as $contexte) {
            $this->assertLessThanOrEqual(5, ModuleCatalogue::principaux($contexte)->count(), $contexte);
        }
    }

    public function test_traduit_les_emoji_connus_en_heroicon(): void
    {
        $this->assertSame('home', ModuleIcons::heroicon('🏠'));
        // Un emoji non mappé rend `null` : l'appelant retombe alors sur l'emoji lui-même, plutôt
        // que d'afficher une icône vide.
        $this->assertNull(ModuleIcons::heroicon('🦆'));
    }
}

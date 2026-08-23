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
        // On relève tout ce qui cloche, puis on l'affirme d'un coup : un groupe mal formé au
        // milieu de la liste ne doit pas cacher les suivants.
        $defauts = [];

        foreach ($groupes as $i => $groupe) {
            foreach (['category', 'label'] as $clef) {
                if (! array_key_exists($clef, $groupe)) {
                    $defauts[] = "groupe #{$i} → clé « {$clef} » absente";
                }
            }

            if (empty($groupe['modules'])) {
                $defauts[] = sprintf('groupe « %s » → aucune entrée, il ne devrait pas être rendu', $groupe['category'] ?? $i);
            }

            foreach ($groupe['modules'] ?? [] as $module) {
                // `*` = module transversal, servi à tous les contextes : profil, notifications,
                // aide, textes légaux. Il appartient donc légitimement à celui-ci.
                if (! in_array($module['context'], ['client', '*'], true)) {
                    $defauts[] = sprintf('%s → contexte « %s » servi à un client', $module['key'], $module['context']);
                }
            }
        }

        $this->assertSame([], $defauts, 'Le catalogue rendu à un client est mal formé.');
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
        $trop = [];

        foreach (['client', 'employe', 'admin', 'client-company', 'provider-company'] as $contexte) {
            $n = ModuleCatalogue::principaux($contexte)->count();

            if ($n > 5) {
                $trop[] = "{$contexte} → {$n} entrées principales";
            }
        }

        $this->assertSame([], $trop, 'Au-delà de cinq entrées principales, la barre cesse d’être un raccourci.');
    }

    public function test_traduit_les_emoji_connus_en_heroicon(): void
    {
        $this->assertSame('home', ModuleIcons::heroicon('🏠'));
        // Un emoji non mappé rend `null` : l'appelant retombe alors sur l'emoji lui-même, plutôt
        // que d'afficher une icône vide.
        $this->assertNull(ModuleIcons::heroicon('🦆'));
    }
}

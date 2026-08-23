<?php

namespace Tests\Feature\Navigation;

use Tests\TestCase;

/**
 * LES DEUX ESPACES SOCIÉTÉ CONSOMMENT LE MÊME REGISTRE QUE LE RESTE.
 *
 * Leurs liens vivaient en dur dans leurs layouts — 11 d'un côté, 6 de l'autre. Deux registres de
 * plus, que personne ne pensait à mettre à jour en ajoutant un module, et qui n'avaient aucune
 * notion de catégorie.
 */
class NavbarSocieteTest extends TestCase
{
    public function test_les_layouts_societe_ne_declarent_plus_leurs_liens_en_dur(): void
    {
        $ecarts = [];

        foreach (['client-company', 'provider-company'] as $layout) {
            $source = (string) file_get_contents(resource_path("views/layouts/{$layout}.blade.php"));

            if (str_contains($source, "'label' =>")) {
                $ecarts[] = "{$layout} : une liste d entrees ecrite en dur subsiste";
            }

            if (! str_contains($source, 'ModuleCatalogue')) {
                $ecarts[] = "{$layout} : ne passe pas par ModuleCatalogue";
            }
        }

        $this->assertSame([], $ecarts, 'Ces gabarits ne tirent pas leur barre du catalogue.');
    }

    public function test_les_layouts_societe_menent_a_leur_page_modules(): void
    {
        // Sans cette porte, les modules non-principaux de ces deux espaces seraient injoignables :
        // leurs layouts sont la seule surface permanente qu'ils possèdent.
        $sansPorte = [];

        foreach ([
            'client-company' => 'client-company.modules',
            'provider-company' => 'provider-company.modules',
        ] as $layout => $route) {
            $source = (string) file_get_contents(resource_path("views/layouts/{$layout}.blade.php"));

            if (! str_contains($source, $route)) {
                $sansPorte[] = "{$layout} : « {$route} » absente";
            }
        }

        $this->assertSame([], $sansPorte, 'Ces gabarits ne menent pas a leur page Modules.');
    }
}

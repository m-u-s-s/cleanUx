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
        foreach (['client-company', 'provider-company'] as $layout) {
            $source = (string) file_get_contents(resource_path("views/layouts/{$layout}.blade.php"));

            $this->assertStringNotContainsString("'label' =>", $source, $layout);
            $this->assertStringContainsString('ModuleCatalogue', $source, $layout);
        }
    }

    public function test_les_layouts_societe_menent_a_leur_page_modules(): void
    {
        // Sans cette porte, les modules non-principaux de ces deux espaces seraient injoignables :
        // leurs layouts sont la seule surface permanente qu'ils possèdent.
        foreach ([
            'client-company' => 'client-company.modules',
            'provider-company' => 'provider-company.modules',
        ] as $layout => $route) {
            $source = (string) file_get_contents(resource_path("views/layouts/{$layout}.blade.php"));

            $this->assertStringContainsString($route, $source, $layout);
        }
    }
}

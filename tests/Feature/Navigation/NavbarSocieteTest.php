<?php

namespace Tests\Feature\Navigation;

use Tests\TestCase;

/** LES DEUX ESPACES SOCIÉTÉ CONSOMMENT LE MÊME REGISTRE, ET LA MÊME BARRE. */
class NavbarSocieteTest extends TestCase
{
    private function barre(): string
    {
        return (string) file_get_contents(resource_path('views/components/barre-societe.blade.php'));
    }

    public function test_la_barre_societe_ne_declare_plus_ses_liens_en_dur(): void
    {
        $ecarts = [];
        $source = $this->barre();

        if (str_contains($source, "'label' =>")) {
            $ecarts[] = 'une liste d entrees ecrite en dur subsiste';
        }

        if (! str_contains($source, 'ModuleCatalogue')) {
            $ecarts[] = 'la barre ne passe pas par ModuleCatalogue';
        }

        $this->assertSame([], $ecarts, 'La barre societe ne tire pas ses liens du catalogue.');
    }

    /**
     * UNE SEULE DEFINITION POUR LES DEUX ESPACES, ET POUR LES ECRANS PERSONNELS QU'ILS
     * ACCUEILLENT. Les deux gabarits portaient chacun sa copie, et elles avaient deja diverge.
     */
    public function test_les_gabarits_montent_la_barre_partagee(): void
    {
        $sansBarre = [];

        foreach (['client-company', 'provider-company', 'app'] as $gabarit) {
            $source = (string) file_get_contents(resource_path("views/layouts/{$gabarit}.blade.php"));

            if (! str_contains($source, 'x-barre-societe')) {
                $sansBarre[] = $gabarit;
            }

            // Une barre ecrite a nouveau dans le gabarit ferait revivre la divergence.
            if (str_contains($source, '<nav data-chrome="primary-nav"')) {
                $sansBarre[] = "{$gabarit} : une barre en dur subsiste";
            }
        }

        $this->assertSame([], $sansBarre, 'Ces gabarits ne montent pas la barre partagee.');
    }

    /**
     * Sans cette porte, les modules non-principaux de ces espaces seraient injoignables :
     * la barre est la seule surface permanente qu'ils possedent.
     */
    public function test_la_barre_mene_a_la_page_modules_de_son_espace(): void
    {
        $this->assertStringContainsString('routeDesModules', $this->barre());
    }
}

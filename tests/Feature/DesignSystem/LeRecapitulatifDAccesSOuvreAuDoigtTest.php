<?php

namespace Tests\Feature\DesignSystem;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * LE DERNIER DEFAUT D'ACCESSIBILITE DES 121 PAGES DU HARNAIS.
 *
 * Le recapitulatif d'acces s'ouvrait par un bouton de 58 x 20 pixels — au-dessous de la cible
 * tactile minimale. Un administrateur qui ouvre `/admin/outils` depuis son telephone visait un
 * trait de vingt pixels de haut. Il portait aussi ses couleurs en dur : sur la nuit, l'en-tete
 * gris clair du tableau restait clair.
 *
 * `<details>` remplace le pliage Alpine : natif, il fonctionne sans JavaScript, le clavier et
 * les lecteurs d'ecran le connaissent, et l'etat n'est plus tenu a deux endroits.
 */
class LeRecapitulatifDAccesSOuvreAuDoigtTest extends TestCase
{
    use RefreshDatabase;

    private function rendu(): string
    {
        return Blade::render('<x-admin.recapitulatif-acces />');
    }

    public function test_le_pliage_est_natif(): void
    {
        $rendu = $this->rendu();

        $this->assertStringContainsString('<details', $rendu);
        $this->assertStringContainsString('<summary', $rendu);
        $this->assertStringContainsString('brio-recap-tete', $rendu);
    }

    /** La cible tactile vit dans le CSS : sans hauteur minimale, le defaut revient. */
    public function test_l_entete_porte_une_hauteur_minimale(): void
    {
        $css = (string) file_get_contents(resource_path('css/composants.css'));

        $this->assertMatchesRegularExpression(
            '/\.brio-recap-tete\s*\{[^}]*min-height:\s*2\.75rem/s',
            $css,
        );
    }

    /**
     * TEMOIN — la reponse de chaque case est LUE, pas seulement dessinee.
     *
     * Le tableau repondait par « ✅ » ou « — ». Un tiret lu a voix haute ne dit pas « non » :
     * la moitie de la matrice etait donc muette pour un lecteur d'ecran.
     */
    public function test_temoin_chaque_case_porte_sa_reponse_en_toutes_lettres(): void
    {
        $rendu = $this->rendu();

        $this->assertStringContainsString('sr-only', $rendu);
        $this->assertStringContainsString('Oui', $rendu);
        $this->assertStringContainsString('Non', $rendu);

        // Le symbole ne doit plus etre annonce en double.
        $this->assertStringContainsString('aria-hidden="true"', $rendu);
    }

    /** TEMOIN — les couleurs en dur ont disparu du composant. */
    public function test_temoin_plus_aucune_couleur_de_palette(): void
    {
        $source = (string) file_get_contents(
            resource_path('views/components/admin/recapitulatif-acces.blade.php')
        );

        // Le commentaire de tete les CITE pour dire ce qui a ete retire : on ne lit que le balisage.
        $balisage = preg_replace('/\{\{--.*?--\}\}/s', '', $source) ?? '';

        $this->assertDoesNotMatchRegularExpression(
            '/\b(bg|text|border)-(white|gray|blue|slate)-?\d*\b/',
            $balisage,
        );
    }
}

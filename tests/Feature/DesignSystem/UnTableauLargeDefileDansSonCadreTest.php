<?php

namespace Tests\Feature\DesignSystem;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LE STYLE DE VERRE DES TABLEAUX AVAIT REPRIS LEUR DEFILEMENT.
 *
 * `responsive.css` fait defiler tout tableau large dans son cadre sous 768px — une regle
 * ecrite pour `/legal/cookies`, ou un tableau de cinq colonnes poussait le document entier.
 *
 * La refonte a pose sur les tableaux `overflow: hidden`, pour rogner leurs coins arrondis.
 * La propriete RACCOURCIE ecrase les deux axes : `overflow-x: auto` de la regle responsive
 * n'existait plus, et le tableau se retrouvait COUPE au lieu de defiler. Mesure sur
 * `/legal/cookies` a 390 px : `scrollWidth` 673 contre `clientWidth` 316 — plus de la moitie
 * inatteignable, sans qu'aucun defilement ne soit propose.
 *
 * ET LE HARNAIS NE POUVAIT PAS LE DIRE CLAIREMENT : le document, lui, ne debordait pas. La
 * page paraissait saine ; c'est le contenu du cadre qui etait perdu.
 *
 * `auto` rogne les coins exactement comme `hidden` : toute valeur autre que `visible` cree
 * un contexte de rognage.
 */
class UnTableauLargeDefileDansSonCadreTest extends TestCase
{
    use RefreshDatabase;

    private function composants(): string
    {
        return (string) file_get_contents(resource_path('css/composants.css'));
    }

    public function test_le_style_de_verre_n_emploie_pas_la_propriete_raccourcie(): void
    {
        $css = $this->composants();

        $debut = strpos($css, 'body:not(.cx-shell) table:not(.brio-opaque) {');
        $this->assertNotFalse($debut, 'La regle de verre des tableaux a disparu.');

        $regle = $this->sansCommentaires(substr($css, $debut, (int) strpos($css, '}', $debut) - $debut));

        $this->assertStringNotContainsString('overflow: hidden', $regle);
        $this->assertStringContainsString('overflow-x: auto', $regle);
    }

    /**
     * TEMOIN — la regle responsive qui rendait le defilement existe TOUJOURS.
     *
     * Sans elle, le test precedent passerait alors que plus rien ne ferait defiler : c'est
     * la conjonction des deux qui tient, pas l'une des deux.
     */
    public function test_temoin_la_regle_responsive_fait_toujours_defiler(): void
    {
        $css = (string) file_get_contents(resource_path('css/responsive.css'));

        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 767\.98px\) \{\s*table \{[^}]*overflow-x:\s*auto/s',
            $css,
        );
    }

    /**
     * TEMOIN — les coins restent rognes.
     *
     * C'etait la raison d'etre du `hidden`. Le remplacer par une valeur qui ne rogne pas
     * echangerait un defaut contre un autre.
     */
    public function test_temoin_les_coins_restent_rognes(): void
    {
        $css = $this->composants();
        $debut = (int) strpos($css, 'body:not(.cx-shell) table:not(.brio-opaque) {');
        $regle = $this->sansCommentaires(substr($css, $debut, (int) strpos($css, '}', $debut) - $debut));

        $this->assertStringContainsString('border-radius:', $regle);

        // `visible` ne rognerait rien : c'est la seule valeur qui casserait l'arrondi.
        $this->assertStringNotContainsString('overflow-x: visible', $regle);
    }

    /*
     * LE COMMENTAIRE CITE LE DEFAUT POUR DIRE CE QUI A ETE RETIRE.
     *
     * Sans cette neutralisation, l'explication se fait accuser de ce qu'elle decrit —
     * le meme piege que le detecteur de boites du navigateur a deja paye.
     */
    private function sansCommentaires(string $css): string
    {
        return preg_replace('/\/\*.*?\*\//s', '', $css) ?? $css;
    }
}

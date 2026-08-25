<?php

namespace Tests\Feature\DesignSystem;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * DEUX SYSTEMES DE STATISTIQUE COHABITAIENT, ET LE MAUVAIS TENAIT LA PAGE.
 *
 * `<x-ui.stat>` portait huit variantes en couleurs Tailwind figees — `bg-amber-50`,
 * `text-red-700`, `border-brand-100` — et douze appels en heritaient. Aucune ne suit le
 * theme : sur la nuit, un fond `-50` reste clair, et le texte `-700` pose dessus devient
 * illisible des que la surface s'assombrit.
 *
 * `.brio-stat*` disait la meme chose en JETONS et n'avait AUCUN appelant. Le systeme qui
 * respectait le theme n'etait nulle part ; celui qui l'ignorait etait partout.
 */
class LaCaseDeStatistiqueSuitLesJetonsTest extends TestCase
{
    use RefreshDatabase;

    private function rendre(string $ton): string
    {
        return Blade::render(
            '<x-ui.stat title="Missions" value="12" tone="'.$ton.'" heroicon="cube" hint="Aujourd\'hui" />'
        );
    }

    public function test_la_case_emploie_les_classes_du_systeme(): void
    {
        $rendu = $this->rendre('slate');

        $this->assertStringContainsString('brio-stat', $rendu);
        $this->assertStringContainsString('brio-stat-value', $rendu);
        $this->assertStringContainsString('brio-stat-label', $rendu);
    }

    /**
     * TEMOIN — aucune couleur de palette ne subsiste, pour AUCUN ton.
     *
     * Les verifier un par un est necessaire : c'est le `match` sur le ton qui portait les
     * couleurs, pas le corps du composant. Un seul ton oublie suffirait a ramener le defaut.
     */
    public function test_temoin_aucun_ton_ne_ramene_une_couleur_de_palette(): void
    {
        foreach (['slate', 'amber', 'red', 'orange', 'rose', 'blue', 'emerald', 'green', 'inconnu'] as $ton) {
            $rendu = $this->rendre($ton);

            $this->assertDoesNotMatchRegularExpression(
                '/\b(bg|text|border|ring)-(amber|red|rose|orange|emerald|green|sky|brand|slate)-\d{2,3}\b/',
                $rendu,
                "Le ton « {$ton} » ramene une couleur de palette.",
            );
        }
    }

    /** Les tons continuent de se DISTINGUER : les fondre tous en neutre serait une regression. */
    public function test_les_tons_restent_distincts(): void
    {
        $this->assertStringContainsString('brio-stat-bad', $this->rendre('red'));
        $this->assertStringContainsString('brio-stat-good', $this->rendre('emerald'));
        $this->assertStringContainsString('brio-stat-warn', $this->rendre('amber'));
        $this->assertStringContainsString('brio-stat-accent', $this->rendre('blue'));
    }

    /**
     * TEMOIN — un ton inconnu tombe sur le neutre, il ne disparait pas.
     *
     * Sans ce controle, un `match` sans branche par defaut ferait lever le rendu au premier
     * ton qu'un ecran inventerait.
     */
    public function test_temoin_un_ton_inconnu_reste_neutre(): void
    {
        $rendu = $this->rendre('turquoise');

        $this->assertStringContainsString('brio-stat', $rendu);
        $this->assertStringNotContainsString('brio-stat-bad', $rendu);
        $this->assertStringNotContainsString('brio-stat-good', $rendu);
    }

    /** L'ancien systeme ne doit plus etre reference par ce composant. */
    public function test_les_classes_de_l_ancien_systeme_ont_disparu(): void
    {
        $source = (string) file_get_contents(resource_path('views/components/ui/stat.blade.php'));

        $this->assertStringNotContainsString('ui-stat', $source);
    }
}

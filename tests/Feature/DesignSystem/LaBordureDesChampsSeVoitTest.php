<?php

namespace Tests\Feature\DesignSystem;

use Tests\TestCase;

/**
 * LA BORDURE D'UN CHAMP ETAIT DU BLANC, SUR UNE CARTE BLANCHE.
 *
 * `--glass-border` vaut rgba(255,255,255,.72) en clair : c'est la TRANCHE d'une carte posee
 * sur le fond de page, et elle fonctionne pour cela. Mais un champ vit DANS une carte, elle
 * meme a rgba(255,255,255,.55) : la bordure disparaissait. Mesure sur /admin/feature-flags
 * et /admin/audit/logs — tous les champs de l'admin, pas une page.
 *
 * Deux regles de `glass.css` atteignaient AUSSI les champs par leurs classes utilitaires
 * (`border-slate-200`, `bg-slate-50`), et Tailwind hisse leur `@layer components` apres tout
 * le reste : elles gagnaient sur `composants.css`. D'ou l'exclusion, plutot qu'une course
 * a la specificite.
 */
class LaBordureDesChampsSeVoitTest extends TestCase
{
    private function css(string $fichier): string
    {
        return (string) file_get_contents(resource_path("css/{$fichier}"));
    }

    public function test_le_jeton_a_une_version_par_theme(): void
    {
        $tokens = $this->css('tokens.css');

        $this->assertSame(2, preg_match_all('/--brio-field-border:/', $tokens),
            'Un jeton defini une seule fois laisse un theme sans valeur.');

        // Le clair doit sortir du blanc : c'est tout le defaut.
        $this->assertMatchesRegularExpression(
            '/:root\s*\{.*?--brio-field-border:\s*rgb\(var\(--brio-ink-rgb\)/s',
            $tokens,
        );

        // TEMOIN — la nuit garde la tranche du verre, qui s'y voit deja.
        $this->assertMatchesRegularExpression(
            '/:root\.dark\s*\{.*?--brio-field-border:\s*var\(--glass-border\)/s',
            $tokens,
        );
    }

    public function test_la_regle_des_champs_emploie_le_jeton(): void
    {
        $composants = $this->css('composants.css');

        $this->assertMatchesRegularExpression(
            '/input\[type="time"\],\s*select,\s*textarea\s*\)\s*:not\(\.brio-opaque\)\s*\{[^}]*border-color:\s*var\(--brio-field-border\)/s',
            $composants,
        );
    }

    public function test_les_neutralisateurs_de_verre_n_atteignent_plus_un_champ(): void
    {
        $glass = $this->css('glass.css');

        foreach (['.bg-slate-50, ', '.border-slate-200, '] as $debut) {
            $pos = strpos($glass, $debut);
            $this->assertNotFalse($pos, "Regle introuvable : {$debut}");

            $selecteur = substr($glass, $pos, (int) strpos($glass, '{', $pos) - $pos);

            $this->assertStringContainsString(':not(input):not(select):not(textarea)', $selecteur,
                "Cette regle repeint la bordure d'un champ en blanc : {$selecteur}");
        }
    }

    public function test_temoin_le_balayage_lit_bien_les_feuilles(): void
    {
        // Sans ce controle, les trois tests ci-dessus passeraient au vert sur un fichier vide.
        $this->assertStringContainsString('--glass-border', $this->css('tokens.css'));
        $this->assertStringContainsString('@layer components', $this->css('glass.css'));
        $this->assertStringContainsString('body:not(.cx-shell)', $this->css('composants.css'));
    }
}

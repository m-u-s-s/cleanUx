<?php

namespace Tests\Feature\DesignSystem;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LES LIENS DES PAGES LEGALES PORTAIENT UNE COULEUR DE PALETTE.
 *
 * Six liens de `mentions`, `privacy` et `terms` etaient en `text-indigo-600` : un indigo
 * qui n'appartient a aucun theme du produit et qui ne bouge pas avec lui. Sur la nuit, il
 * tombait a 3,06:1 la ou 4,5 sont exiges — sur des pages que TOUT LE MONDE peut lire, et
 * dont le contenu engage juridiquement.
 *
 * `--brio-accent-texte` existe pour cela : l'accent de marque ramene a une valeur lisible
 * en texte (`#ff8a3d` donne 2,27:1), avec ses deux versions.
 */
class LesLiensLegauxSuiventLeThemeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * UNE COULEUR DE PALETTE SUR UN LIEN OU UN BOUTON.
     *
     * Les BOUTONS comptent aussi : celui des preferences cookies portait `bg-indigo-600`,
     * la meme couleur hors palette, sur la meme page publique. Ne regarder que les `<a>`
     * l'aurait laisse passer — et un bouton se voit davantage qu'un lien.
     */
    private const HORS_PALETTE = '/<(a|button)\b[^>]*\b(text|bg)-(indigo|blue|sky|violet)-\d{3}\b/';

    /** @return array<int, string> */
    private function pagesLegales(): array
    {
        return glob(resource_path('views/legal/*.blade.php')) ?: [];
    }

    public function test_aucun_lien_legal_ne_porte_une_couleur_de_palette(): void
    {
        $coupables = [];

        foreach ($this->pagesLegales() as $chemin) {
            $contenu = (string) file_get_contents($chemin);

            if (preg_match(self::HORS_PALETTE, $contenu) === 1) {
                $coupables[] = basename($chemin);
            }
        }

        $this->assertSame([], $coupables, implode(', ', $coupables));
    }

    /**
     * TEMOIN — le detecteur reconnait VRAIMENT une couleur de palette sur un lien.
     *
     * Sans lui, une expression trop stricte rendrait un tableau vide en permanence : le test
     * precedent passerait en ne mesurant rien.
     */
    public function test_temoin_le_detecteur_reconnait_une_couleur_figee(): void
    {
        $faux = '<p>Voir <a href="#" class="text-indigo-600 underline">la politique</a>.</p>';

        $this->assertSame(1, preg_match(self::HORS_PALETTE, $faux));

        // Et le bouton, qui a bel et bien existe sur `/legal/cookies`.
        $bouton = '<button class="rounded-lg bg-indigo-600 text-white">Modifier</button>';

        $this->assertSame(1, preg_match(self::HORS_PALETTE, $bouton));
    }

    /** TEMOIN — et il n'accuse pas les classes du systeme. */
    public function test_temoin_le_detecteur_n_accuse_pas_la_classe_du_systeme(): void
    {
        $juste = '<p>Voir <a href="#" class="brio-lien">la politique</a>.</p>';

        $this->assertSame(0, preg_match(self::HORS_PALETTE, $juste));

        $bouton = '<button class="brio-btn brio-btn-accent">Modifier</button>';

        $this->assertSame(0, preg_match(self::HORS_PALETTE, $bouton));
    }

    public function test_la_classe_du_systeme_est_definie_et_employee(): void
    {
        $css = (string) file_get_contents(resource_path('css/composants.css'));

        $this->assertMatchesRegularExpression(
            '/\.brio-lien\s*\{[^}]*color:\s*var\(--brio-accent-texte\)/s',
            $css,
        );

        // Le soulignement reste : la couleur ne doit jamais porter seule « ceci est un lien ».
        $this->assertMatchesRegularExpression(
            '/\.brio-lien\s*\{[^}]*text-decoration:\s*underline/s',
            $css,
        );

        $employee = 0;

        foreach ($this->pagesLegales() as $chemin) {
            $employee += substr_count((string) file_get_contents($chemin), 'class="brio-lien"');
        }

        $this->assertSame(6, $employee);
    }

    /**
     * TEMOIN — le jeton a bien DEUX versions.
     *
     * Une seule valeur suffirait a passer les tests precedents tout en restant illisible sur
     * l'un des deux fonds : c'est exactement le defaut qu'on repare.
     */
    public function test_temoin_le_jeton_a_une_version_par_theme(): void
    {
        $tokens = (string) file_get_contents(resource_path('css/tokens.css'));

        $this->assertSame(
            2,
            preg_match_all('/--brio-accent-texte:\s*#[0-9a-f]{6}/i', $tokens),
            'Le jeton doit avoir une valeur claire ET une valeur sombre.',
        );
    }
}

<?php

namespace Tests\Feature\DesignSystem;

use Tests\TestCase;

/** Le bouton de danger : une seule definition autonome, et un aplat lisible dans les deux themes. */
class LeBoutonDangerResteLisibleTest extends TestCase
{
    /** @return array<string, list<string>> Les selecteurs qui definissent chaque classe de bouton. */
    private function definitions(): array
    {
        $trouve = [];

        foreach (glob(resource_path('css/*.css')) ?: [] as $feuille) {
            $contenu = (string) file_get_contents($feuille);
            $nom = basename($feuille);

            preg_match_all('/(^|\})\s*([^{}@\/]*\.brio-btn-(?:danger|primary|secondary)[^{}]*)\{/m', $contenu, $trouves);

            foreach ($trouves[2] as $selecteur) {
                $selecteur = trim((string) preg_replace('/\s+/', ' ', $selecteur));

                foreach (['danger', 'primary', 'secondary'] as $classe) {
                    if (str_contains($selecteur, '.brio-btn-'.$classe)) {
                        $trouve['brio-btn-'.$classe][] = $nom.' → '.$selecteur;
                    }
                }
            }
        }

        return $trouve;
    }

    /** Un selecteur est AUTONOME s'il ne porte que la classe elle-meme, sans base ni contexte. */
    private function autonomes(string $classe): array
    {
        return array_values(array_filter(
            $this->definitions()[$classe] ?? [],
            fn (string $site): bool => str_ends_with($site, '→ .'.$classe),
        ));
    }

    public function test_le_bouton_de_danger_n_a_qu_une_definition_autonome(): void
    {
        $this->assertCount(1, $this->autonomes('brio-btn-danger'),
            'Deux feuilles definissent `.brio-btn-danger` sans base : chacune gagne sur une propriete, '
            .'et le texte finit rouge sur rouge. Sites : '.implode(' | ', $this->autonomes('brio-btn-danger')));
    }

    /** L'aplat plein reste joignable : il exige sa base `.brio-btn`, comme ses freres du meme fichier. */
    public function test_l_aplat_plein_exige_sa_base(): void
    {
        $sites = implode(' | ', $this->definitions()['brio-btn-danger'] ?? []);

        $this->assertStringContainsString('.brio-btn.brio-btn-danger', $sites,
            'Le modificateur plein ne declare plus sa base : plus rien ne peint le bouton de `saved-payment-methods`.');
    }

    /**
     * TEMOIN. Sans lui, le test ci-dessus passerait au vert si l'extraction ne voyait plus aucune
     * definition — il mesurerait alors sa propre panne.
     */
    public function test_temoin_l_extraction_voit_les_deux_freres(): void
    {
        $this->assertCount(1, $this->autonomes('brio-btn-primary'));
        $this->assertCount(1, $this->autonomes('brio-btn-secondary'));
    }

    public function test_le_bouton_de_danger_suit_le_theme_sombre_comme_ses_freres(): void
    {
        $css = (string) file_get_contents(resource_path('css/tool-mode.css'));

        foreach (['danger', 'primary', 'secondary'] as $classe) {
            $this->assertMatchesRegularExpression('/\.dark \.brio-btn-'.$classe.'\b/', $css,
                "`.brio-btn-{$classe}` n'a pas de variante sombre, ses freres en ont une.");
        }
    }

    /** La regle sombre du bouton autonome ne doit pas repeindre le modificateur plein. */
    public function test_la_regle_sombre_epargne_l_aplat_plein(): void
    {
        $css = (string) file_get_contents(resource_path('css/tool-mode.css'));

        $this->assertMatchesRegularExpression('/\.dark \.brio-btn-danger:not\(\.brio-btn\)/', $css,
            'La regle sombre autonome mord sur `.brio-btn.brio-btn-danger` : meme specificite, chargee apres.');
    }

    /** Le jeton d'APLAT est distinct du jeton de TEXTE : l'un porte du blanc, l'autre s'ecrit. */
    public function test_l_aplat_de_danger_porte_du_blanc_dans_les_deux_themes(): void
    {
        foreach (['clair', 'sombre'] as $theme) {
            $aplat = $this->jeton('--brio-danger-aplat', $theme);

            $this->assertNotNull($aplat, "Le jeton d'aplat manque en mode {$theme}.");
            $this->assertGreaterThanOrEqual(4.5, $this->contraste('#ffffff', $aplat),
                "Le blanc sur l'aplat de danger tombe sous 4,5:1 en mode {$theme} ({$aplat}).");
        }
    }

    /**
     * TEMOIN. Le calcul doit REFUSER une paire connue mauvaise, sinon le test ci-dessus passerait
     * au vert quelle que soit la couleur.
     */
    public function test_temoin_le_calcul_refuse_une_paire_illisible(): void
    {
        $this->assertLessThan(4.5, $this->contraste('#ffffff', '#f87171'));
        $this->assertLessThan(2.0, $this->contraste('#b91c1c', '#dc2626'));
    }

    private function jeton(string $nom, string $theme): ?string
    {
        $css = (string) file_get_contents(resource_path('css/tokens.css'));
        $debut = strpos($css, $theme === 'sombre' ? ':root.dark {' : ':root {');

        if ($debut === false) {
            return null;
        }

        $bloc = substr($css, $debut, (int) (strpos($css, "\n}", $debut) ?: strlen($css)) - $debut);

        return preg_match('/'.preg_quote($nom, '/').':\s*(#[0-9a-fA-F]{3,8})/', $bloc, $m) ? $m[1] : null;
    }

    private function contraste(string $a, string $b): float
    {
        $luminance = function (string $hex): float {
            [$r, $g, $bl] = array_map(
                fn (string $paire): float => hexdec($paire) / 255,
                str_split(substr(ltrim($hex, '#'), 0, 6), 2),
            );

            $canal = fn (float $v): float => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;

            return 0.2126 * $canal($r) + 0.7152 * $canal($g) + 0.0722 * $canal($bl);
        };

        $clair = max($luminance($a), $luminance($b));
        $sombre = min($luminance($a), $luminance($b));

        return round(($clair + 0.05) / ($sombre + 0.05), 2);
    }
}

<?php

namespace Tests\Feature\DesignSystem;

use Tests\TestCase;

/**
 * GARDE CADREE SUR L'AUTOMATISATION. `AucuneCouleurEnDurDansLesVuesTest` ne connait que `#hex`
 * et `rgb()`, et ne lit que les vues : une classe de palette Tailwind lui echappe entierement.
 */
class LAutomatisationEmploieLesJetonsTest extends TestCase
{
    /**
     * Les palettes a echelle numerique reellement disponibles : celles de Tailwind, plus celles
     * que `tailwind.config.js` ajoute sous `extend.colors` (brand, surface, success, warning, danger).
     *
     * @var list<string>
     */
    private const PALETTES = [
        'slate', 'gray', 'zinc', 'neutral', 'stone',
        'red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 'teal',
        'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia', 'pink', 'rose',
        'brand', 'surface', 'success', 'warning', 'danger',
    ];

    /** Les deux palettes du depot SANS echelle numerique — literales aussi, invisibles a l'autre motif. */
    private const NUANCES_NOMMEES = [
        'swift-red', 'swift-green', 'swift-blue',
        'accent-amber-deep', 'accent-amber', 'accent-cyan', 'accent-violet',
    ];

    private function motif(): string
    {
        $palettes = implode('|', self::PALETTES);
        $nommees = implode('|', self::NUANCES_NOMMEES);

        // `!` optionnel, prefixe d'etat optionnel (`hover:`, `dark:`, `md:`), puis la classe.
        return '/(?<![\w-])!?(?:bg|text|border|ring|from|to)-(?:(?:'.$palettes.')-\d{2,3}|(?:'.$nommees.'))(?![\w-])/';
    }

    /** @return array<string, list<string>> fichier relatif => classes fautives */
    private function classesLitterales(): array
    {
        $trouvees = [];

        foreach ($this->fichiersDuPerimetre() as $relatif => $chemin) {
            $code = (string) file_get_contents($chemin);

            if (str_ends_with($chemin, '.blade.php')) {
                $code = preg_replace('/\{\{--.*?--\}\}/s', '', $code) ?? $code;
            }

            if (preg_match_all($this->motif(), $code, $m) > 0) {
                $trouvees[$relatif] = array_values(array_unique($m[0]));
            }
        }

        return $trouvees;
    }

    /** @return array<string, string> chemin relatif => chemin absolu */
    private function fichiersDuPerimetre(): array
    {
        $fichiers = [];

        $vues = resource_path('views/livewire/admin');

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($vues)) as $fichier) {
            if (! $fichier->isFile() || ! str_ends_with($fichier->getFilename(), '.blade.php')) {
                continue;
            }

            $chemin = str_replace(chr(92), '/', $fichier->getPathname());
            $relatif = ltrim(str_replace(str_replace(chr(92), '/', $vues), '', $chemin), '/');

            if (str_starts_with($relatif, 'automation')) {
                $fichiers['resources/views/livewire/admin/'.$relatif] = $chemin;
            }
        }

        $composants = array_merge(
            [app_path('Livewire/Admin/AutomationCenter.php')],
            glob(app_path('Livewire/Admin/Automation/*.php')) ?: []
        );

        // LE CHEMIN RAPPORTE DOIT EXISTER : le reconstruire a partir du dossier parent doublait
        // « Admin » pour le composant qui vit a la racine du dossier.
        $racine = str_replace(chr(92), '/', base_path()).'/';

        foreach ($composants as $chemin) {
            $chemin = str_replace(chr(92), '/', $chemin);
            $fichiers[str_replace($racine, '', $chemin)] = $chemin;
        }

        return $fichiers;
    }

    /**
     * TEMOIN — le balayage voit bien les deux perimetres, et le motif sait distinguer une classe
     * de palette d'une classe de taille. Sans lui, un chemin faux rendrait un vert sur du neant.
     */
    public function test_temoin_le_balayage_voit_les_deux_perimetres_et_reconnait_une_classe(): void
    {
        $fichiers = $this->fichiersDuPerimetre();

        $vues = array_filter(array_keys($fichiers), fn (string $f): bool => str_starts_with($f, 'resources/'));
        $php = array_filter(array_keys($fichiers), fn (string $f): bool => str_starts_with($f, 'app/'));

        $this->assertGreaterThanOrEqual(3, count($vues), 'Le balayage ne voit presque aucune vue : le chemin est faux.');
        $this->assertSame(5, count($php), 'Les cinq composants de la phase doivent etre balayes.');

        $motif = $this->motif();

        $this->assertSame(1, preg_match($motif, 'class="bg-emerald-50"'), 'Une classe de palette doit etre reconnue.');
        $this->assertSame(1, preg_match($motif, 'class="!border-amber-200"'), 'Le prefixe « ! » ne doit rien cacher.');
        $this->assertSame(1, preg_match($motif, 'class="hover:text-slate-700"'), 'Un prefixe d’etat non plus.');
        $this->assertSame(1, preg_match($motif, 'from-slate-950 to-blue-900'), 'Un degrade non plus.');
        $this->assertSame(1, preg_match($motif, 'class="text-swift-red"'), 'Une nuance nommee du depot aussi.');
        $this->assertSame(0, preg_match($motif, 'class="text-sm text-xs"'), 'Une taille n’est pas une couleur.');
        $this->assertSame(0, preg_match($motif, 'class="brio-btn brio-btn-primary"'), 'Une classe du systeme non plus.');
        $this->assertSame(0, preg_match($motif, 'style="color: var(--brio-ink);"'), 'Un jeton non plus.');
    }

    public function test_aucune_vue_ni_composant_d_automatisation_n_ecrit_de_classe_de_palette(): void
    {
        $ecarts = [];

        foreach ($this->classesLitterales() as $fichier => $classes) {
            $ecarts[] = $fichier.' : '.implode(', ', $classes);
        }

        $this->assertSame(
            [],
            $ecarts,
            "Une classe de palette Tailwind ne suit ni le theme ni le mode sombre.\n"
            .'Employez un jeton : `style="color: var(--brio-ink)"`, ou une classe `brio-*`.',
        );
    }
}

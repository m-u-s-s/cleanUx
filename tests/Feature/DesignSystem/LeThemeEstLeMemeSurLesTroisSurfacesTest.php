<?php

namespace Tests\Feature\DesignSystem;

use Tests\TestCase;

/**
 * Le web et le natif portent la MÊME palette, et rien ne le garantissait.
 * Une couleur changée d'un côté laissait l'autre en arrière, sans qu'aucun outil ne le dise.
 */
class LeThemeEstLeMemeSurLesTroisSurfacesTest extends TestCase
{
    /**
     * Jeton CSS du web => chemin dans `mobile/shared/src/theme/colors.ts`.
     *
     * Ne figurent ici que les couleurs RÉELLEMENT partagées. Les échelles `brand`, `surface`,
     * `success`, `warning` et `danger` n'y sont pas : le web les tient de Tailwind, le natif doit
     * les déclarer lui-même, et exiger qu'elles coïncident n'aurait aucun sens.
     *
     * @var array<string, string>
     */
    private const PARTAGEES = [
        '--cx-amber' => 'accent.amber',
        '--cx-amber-deep' => 'accent.amberDeep',
        '--cx-cyan' => 'accent.cyan',
        '--cx-violet' => 'accent.violet',
        '--brio-ink' => 'mode.tool.ink',
        '--brio-muted' => 'mode.tool.muted',
        '--cx-night' => 'mode.showcase.night',
        '--cx-night-soft' => 'mode.showcase.nightSoft',
        '--cx-panel' => 'mode.showcase.panel',
        '--cx-text' => 'mode.showcase.text',
        '--cx-muted' => 'mode.showcase.muted',
    ];

    private function jetonWeb(string $nom): ?string
    {
        $css = (string) file_get_contents(resource_path('css/tokens.css'));

        if (preg_match('/'.preg_quote($nom, '/').'\s*:\s*(#[0-9a-fA-F]{3,8})\s*;/', $css, $m) === 1) {
            return strtolower($m[1]);
        }

        // Un jeton peut se dériver d'un autre : `--brio-ink: rgb(var(--brio-ink-rgb))`.
        // Sans suivre la dérivation, le garde croirait le jeton disparu.
        if (preg_match('/'.preg_quote($nom, '/').'\s*:\s*rgba?\(\s*var\((--[\w-]+)\)/', $css, $m) === 1) {
            return $this->composantesEnHexa($m[1], $css);
        }

        return null;
    }

    /** Rend `15 23 42` sous la forme `#0f172a`, pour comparer ce qui est comparable. */
    private function composantesEnHexa(string $nom, string $css): ?string
    {
        if (preg_match('/'.preg_quote($nom, '/').'\s*:\s*(\d+)[\s,]+(\d+)[\s,]+(\d+)\s*;/', $css, $c) !== 1) {
            return null;
        }

        return sprintf('#%02x%02x%02x', (int) $c[1], (int) $c[2], (int) $c[3]);
    }

    /**
     * Résout un chemin complet — `mode.showcase.muted`, pas seulement `muted`.
     * `muted` existe dans DEUX sous-objets : chercher la dernière clé seule rend la mauvaise.
     */
    private function jetonNatif(string $chemin): ?string
    {
        $portee = (string) file_get_contents(base_path('mobile/shared/src/theme/colors.ts'));
        $segments = explode('.', $chemin);
        $feuille = array_pop($segments);

        /*
         * On descend d'un sous-objet à l'autre en COMPTANT les accolades. Couper au premier
         * `},` refermerait le premier enfant — `mode` contient `tool` puis `showcase`, et la
         * coupe naïve perdait tout `showcase`.
         */
        foreach ($segments as $parent) {
            $debut = strpos($portee, $parent.': {');

            if ($debut === false) {
                return null;
            }

            $i = $debut + strlen($parent) + 2;
            $profondeur = 0;
            $fin = $i;

            for ($j = $i; $j < strlen($portee); $j++) {
                if ($portee[$j] === '{') {
                    $profondeur++;
                } elseif ($portee[$j] === '}') {
                    $profondeur--;

                    if ($profondeur === 0) {
                        $fin = $j;
                        break;
                    }
                }
            }

            $portee = substr($portee, $i, $fin - $i);
        }

        return preg_match('/\b'.preg_quote($feuille, '/').'\s*:\s*\'(#[0-9a-fA-F]{3,8})\'/', $portee, $m) === 1
            ? strtolower($m[1])
            : null;
    }

    /**
     * TÉMOIN — les deux fichiers existent et rendent bien des couleurs.
     * Sans lui, deux `null` compareraient égaux et le test passerait sur du vide.
     */
    public function test_temoin_les_deux_sources_rendent_des_couleurs(): void
    {
        $this->assertSame('#ffb648', $this->jetonWeb('--cx-amber'));
        $this->assertSame('#ffb648', $this->jetonNatif('accent.amber'));
        $this->assertNull($this->jetonWeb('--cx-jeton-qui-n-existe-pas'));
    }

    public function test_chaque_couleur_partagee_vaut_la_meme_des_deux_cotes(): void
    {
        $ecarts = [];

        foreach (self::PARTAGEES as $css => $natif) {
            $w = $this->jetonWeb($css);
            $n = $this->jetonNatif($natif);

            if ($w === null) {
                $ecarts[] = "{$css} : absent de resources/css/tokens.css";

                continue;
            }

            if ($n === null) {
                $ecarts[] = "{$natif} : absent de mobile/shared/src/theme/colors.ts";

                continue;
            }

            if ($w !== $n) {
                $ecarts[] = "{$css} vaut {$w} sur le web, {$n} en natif ({$natif})";
            }
        }

        $this->assertSame([], $ecarts, 'Le web et le natif ne portent plus la même palette.');
    }

    /**
     * Jeton CSS du web => clé dans `theme.extend.colors.accent` de `tailwind.config.js`.
     * Troisième copie des mêmes accents : les classes `text-accent-amber` la lisent, pas `tokens.css`.
     *
     * @var array<string, string>
     */
    private const ACCENTS_TAILWIND = [
        '--cx-amber' => 'amber',
        '--cx-amber-deep' => "'amber-deep'",
        '--cx-cyan' => 'cyan',
        '--cx-violet' => 'violet',
    ];

    private function accentTailwind(string $cle): ?string
    {
        $js = (string) file_get_contents(base_path('tailwind.config.js'));
        $debut = strpos($js, 'accent: {');

        if ($debut === false) {
            return null;
        }

        $bloc = substr($js, $debut, 400);

        return preg_match('/'.preg_quote($cle, '/').'\s*:\s*[\x22\x27](#[0-9a-fA-F]{3,8})[\x22\x27]/', $bloc, $m) === 1
            ? strtolower($m[1])
            : null;
    }

    /**
     * Les classes `text-accent-amber` lisent Tailwind, pas `tokens.css`. Une couleur corrigée
     * dans l'un et pas dans l'autre donne deux ambres à l'écran, côte à côte.
     */
    public function test_les_accents_de_tailwind_valent_les_memes_que_les_jetons_css(): void
    {
        $ecarts = [];

        foreach (self::ACCENTS_TAILWIND as $css => $cle) {
            $w = $this->jetonWeb($css);
            $tw = $this->accentTailwind($cle);

            if ($w === null || $tw === null) {
                $ecarts[] = "{$css} / accent.{$cle} : absent d’un des deux côtés";

                continue;
            }

            if ($w !== $tw) {
                $ecarts[] = "{$css} vaut {$w} dans tokens.css, {$tw} dans tailwind.config.js (accent.{$cle})";
            }
        }

        $this->assertSame([], $ecarts, 'Tailwind et les jetons CSS ne portent plus les mêmes accents.');
    }

    /**
     * Rayon CSS du web => clé dans `mobile/shared/src/theme/radius.ts`.
     * Les cinq valeurs coïncidaient, alignées à la main.
     *
     * @var array<string, string>
     */
    private const RAYONS = [
        '--cx-radius-sm' => 'sm',
        '--cx-radius-md' => 'md',
        '--cx-radius-lg' => 'lg',
        '--cx-radius-xl' => 'xl',
        '--cx-radius-pill' => 'pill',
    ];

    /**
     * Durée CSS du web => clé dans `animation.ts`.
     *
     * @var array<string, string>
     */
    private const DUREES = [
        '--cx-dur-fast' => 'fast',
        '--cx-dur' => 'base',
        '--cx-dur-slow' => 'slow',
    ];

    /** Lit un nombre suivi d'une unité — `22px`, `280ms`. */
    private function nombreWeb(string $nom): ?int
    {
        $css = (string) file_get_contents(resource_path('css/tokens.css'));

        return preg_match('/'.preg_quote($nom, '/').'\s*:\s*(\d+)(?:px|ms)\s*;/', $css, $m) === 1
            ? (int) $m[1]
            : null;
    }

    private function nombreNatif(string $fichier, string $cle): ?int
    {
        $ts = (string) file_get_contents(base_path("mobile/shared/src/theme/{$fichier}"));

        // Les clés numériques comme `2xs` sont citées : les deux formes doivent passer.
        return preg_match('/[\x22\x27]?'.preg_quote($cle, '/').'[\x22\x27]?\s*:\s*(\d+)/', $ts, $m) === 1
            ? (int) $m[1]
            : null;
    }

    public function test_chaque_rayon_partage_vaut_le_meme_des_deux_cotes(): void
    {
        $ecarts = [];

        foreach (self::RAYONS as $css => $cle) {
            $w = $this->nombreWeb($css);
            $n = $this->nombreNatif('radius.ts', $cle);

            if ($w === null || $n === null) {
                $ecarts[] = "{$css} / radius.{$cle} : absent d’un des deux côtés";

                continue;
            }

            if ($w !== $n) {
                $ecarts[] = "{$css} vaut {$w}px sur le web, {$n} en natif (radius.{$cle})";
            }
        }

        $this->assertSame([], $ecarts, 'Le web et le natif n’arrondissent plus pareil.');
    }

    /**
     * Durées et courbe d'assouplissement. Une transition plus lente d'un côté se voit
     * immédiatement quand on passe de l'application au navigateur.
     */
    public function test_le_mouvement_a_les_memes_durees_des_deux_cotes(): void
    {
        $ecarts = [];

        foreach (self::DUREES as $css => $cle) {
            $w = $this->nombreWeb($css);
            $n = $this->nombreNatif('animation.ts', $cle);

            if ($w === null || $n === null) {
                $ecarts[] = "{$css} / animation.duration.{$cle} : absent d’un des deux côtés";

                continue;
            }

            if ($w !== $n) {
                $ecarts[] = "{$css} vaut {$w}ms sur le web, {$n} en natif (duration.{$cle})";
            }
        }

        $tokens = (string) file_get_contents(resource_path('css/tokens.css'));
        $anim = (string) file_get_contents(base_path('mobile/shared/src/theme/animation.ts'));

        // La même courbe, écrite dans les deux syntaxes : `cubic-bezier(...)` et un tableau.
        preg_match('/--cx-ease:\s*cubic-bezier\(([^)]+)\)/', $tokens, $mw);
        preg_match('/default:\s*\[([^\]]+)\]/', $anim, $mn);

        $normaliser = static fn (string $v): string => implode(',', array_map(
            static fn (string $x): string => rtrim(rtrim(number_format((float) trim($x), 4, '.', ''), '0'), '.'),
            explode(',', $v),
        ));

        if (($mw[1] ?? null) === null || ($mn[1] ?? null) === null) {
            $ecarts[] = 'la courbe d’assouplissement est absente d’un des deux côtés';
        } elseif ($normaliser($mw[1]) !== $normaliser($mn[1])) {
            $ecarts[] = "la courbe vaut ({$mw[1]}) sur le web, [{$mn[1]}] en natif";
        }

        $this->assertSame([], $ecarts, 'Le mouvement ne se ressemble plus d’une surface à l’autre.');
    }

    /**
     * La vue mobile du navigateur emploie UN SEUL point de rupture : celui de Tailwind.
     * `max-width: 768px` chevauche `min-width: 768px` — à 768 px exactement, les deux s'appliquent.
     */
    public function test_aucune_media_query_ne_chevauche_le_point_de_rupture_de_tailwind(): void
    {
        $chevauchements = [];

        foreach (glob(resource_path('css/*.css')) as $chemin) {
            $css = (string) file_get_contents($chemin);

            if (preg_match_all('/@media[^{]*max-width:\s*768px/', $css, $m)) {
                $chevauchements[] = basename($chemin).' : '.count($m[0]).' × `max-width: 768px`';
            }
        }

        $this->assertSame(
            [],
            $chevauchements,
            'Employer `max-width: 767.98px` : à 768 px exactement, `max-width: 768px` et le `md:` de '
            .'Tailwind s’appliquent tous les deux.',
        );
    }

    /**
     * Un contenu large défile dans son cadre, jamais la page.
     * Un tableau de cinq colonnes fait 524 px : sur 390 px, il emportait le document entier.
     */
    public function test_la_regle_de_debordement_mobile_est_posee(): void
    {
        $css = (string) file_get_contents(resource_path('css/responsive.css'));

        $manques = [];

        foreach ([
            'max-width: 767.98px' => 'le point de rupture mobile',
            'overflow-x: auto' => 'le défilement dans le cadre',
            'min-width: max-content' => 'la largeur qui empêche les colonnes de s’écraser',
            'overflow-x: clip' => 'le filet de sécurité sur le document',
        ] as $motif => $role) {
            if (! str_contains($css, $motif)) {
                $manques[] = "{$motif} — {$role}";
            }
        }

        $this->assertSame([], $manques, 'La feuille de vue mobile a perdu une de ses règles.');

        // Et elle doit être CHARGÉE : une feuille orpheline ne s'applique nulle part.
        $this->assertStringContainsString(
            "@import './responsive.css';",
            (string) file_get_contents(resource_path('css/app.css')),
            'responsive.css n’est plus importée par app.css.',
        );
    }
}

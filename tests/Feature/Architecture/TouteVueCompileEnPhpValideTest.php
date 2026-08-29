<?php

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * UNE VUE QUI NE COMPILE PAS EST UN 500, ET RIEN NE LE DIT AVANT.
 *
 * PHPStan ne lit pas les Blade, la suite ne voit que les pages qu'elle ouvre, et le style
 * n'y touche pas. Une vue mal formee dort donc jusqu'au premier visiteur.
 *
 * LE PIEGE QUI A MOTIVE CETTE GARDE — mesure le 2026-09-28 : un `@php(…)` EN LIGNE se fait
 * fermer par le premier `@endphp` rencontre plus bas, meme s'il appartient a un autre bloc,
 * meme s'il est ecrit dans un commentaire. Blade avale alors tout ce qui les separe et le
 * rend en texte brut ; le PHP produit ne tient plus debout.
 */
class TouteVueCompileEnPhpValideTest extends TestCase
{
    /** @return list<string> */
    private function vues(): array
    {
        $vues = [];
        $racine = resource_path('views');

        $iterateur = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($racine));

        /** @var \SplFileInfo $fichier */
        foreach ($iterateur as $fichier) {
            if ($fichier->isFile() && str_ends_with($fichier->getFilename(), '.blade.php')) {
                $vues[] = $fichier->getPathname();
            }
        }

        sort($vues);

        return $vues;
    }

    public function test_chaque_vue_compile_en_php_valide(): void
    {
        $cassees = [];

        foreach ($this->vues() as $chemin) {
            $source = (string) file_get_contents($chemin);

            try {
                $compile = Blade::compileString($source);
            } catch (\Throwable $e) {
                $cassees[] = $this->relatif($chemin).' — compilation impossible : '.$e->getMessage();

                continue;
            }

            // Blade ecrit toujours `<?php ` avec une espace. `<?php(` est la signature
            // d'un `@php(…)` avale : la directive a ete rendue telle quelle.
            if (str_contains($compile, '<?php(')) {
                $cassees[] = $this->relatif($chemin).' — un `@php(…)` a été rendu tel quel : il a été fermé par un `@endphp` situé plus bas.';

                continue;
            }

            $desequilibre = $this->desequilibreDesBlocs($compile);

            if ($desequilibre !== null) {
                $cassees[] = $this->relatif($chemin).' — '.$desequilibre;
            }
        }

        $this->assertSame([], $cassees, sprintf(
            "%d vue(s) produisent du PHP invalide :\n  %s",
            count($cassees),
            implode("\n  ", $cassees),
        ));
    }

    /**
     * LE PIEGE, PRIS A LA SOURCE.
     *
     * Le test ci-dessus attrape le symptome ; celui-ci nomme la cause, pour que le message
     * d'echec dise quoi corriger au lieu de « unexpected endif ».
     */
    public function test_aucune_vue_ne_melange_le_php_en_ligne_et_le_php_en_bloc(): void
    {
        $melangees = [];

        foreach ($this->vues() as $chemin) {
            $lignes = explode("\n", (string) file_get_contents($chemin));

            $premiereEnLigne = null;

            foreach ($lignes as $n => $ligne) {
                if (preg_match('/@php\s*\(/', $ligne) === 1) {
                    $premiereEnLigne = $n + 1;
                    break;
                }
            }

            if ($premiereEnLigne === null) {
                continue;
            }

            foreach (array_slice($lignes, $premiereEnLigne) as $n => $ligne) {
                $nu = trim($ligne);

                if ($nu === '@php' || (str_starts_with($nu, '@php') && preg_match('/@php\s*\(/', $nu) !== 1)) {
                    $melangees[] = sprintf(
                        '%s — `@php(…)` ligne %d, puis un bloc ligne %d',
                        $this->relatif($chemin),
                        $premiereEnLigne,
                        $premiereEnLigne + $n + 1,
                    );

                    break;
                }
            }
        }

        $this->assertSame([], $melangees, sprintf(
            "%d vue(s) mêlent les deux formes de `@php` :\n  %s\n\n".
            'La seconde se fait fermer par la première : tenez-vous à une seule forme par vue.',
            count($melangees),
            implode("\n  ", $melangees),
        ));
    }

    /**
     * TEMOIN — la mesure repere bien une vue cassee, et EPARGNE une vue saine.
     *
     * Sans les deux sens, un balayage a zero defaut ne prouverait rien : il pourrait aussi
     * bien ne rien mesurer du tout.
     */
    public function test_temoin_la_mesure_repere_une_vue_cassee(): void
    {
        $casse = Blade::compileString(
            '@php($a = 1)
@if ($a)
@php
$b = 2;
@endphp
x
@endif'
        );

        $this->assertStringContainsString(
            '<?php(',
            $casse,
            'La signature du `@php(…)` avalé a disparu : la mesure ne repère plus rien.'
        );

        $saine = Blade::compileString('@php($a = 1)
@if ($a)
x
@endif');

        $this->assertStringNotContainsString('<?php(', $saine);
        $this->assertNull($this->desequilibreDesBlocs($saine));
    }

    /**
     * LA SYNTAXE ALTERNATIVE, ET ELLE SEULE.
     *
     * Blade n'ecrit que `if (…):` / `endif;`. Un `endif` qui ne ferme pas un `if` ouvert
     * de cette facon signe une vue dont le PHP ne tient plus debout — c'est exactement ce
     * que produit le melange des deux formes de `@php`.
     */
    private function desequilibreDesBlocs(string $php): ?string
    {
        $jetons = @token_get_all($php);
        $nombre = count($jetons);
        $pile = [];

        for ($i = 0; $i < $nombre; $i++) {
            $jeton = $jetons[$i];

            if (! is_array($jeton)) {
                continue;
            }

            $nom = token_name($jeton[0]);

            if (in_array($nom, ['T_IF', 'T_ELSEIF', 'T_FOREACH', 'T_FOR', 'T_WHILE', 'T_SWITCH'], true)) {
                $profondeur = 0;
                $k = $i;

                for (; $k < $nombre; $k++) {
                    if ($jetons[$k] === '(') {
                        $profondeur++;
                    } elseif ($jetons[$k] === ')') {
                        $profondeur--;

                        if ($profondeur === 0) {
                            break;
                        }
                    }
                }

                $suivant = null;

                for ($m = $k + 1; $m < $nombre; $m++) {
                    $t = $jetons[$m];

                    if (is_array($t) && in_array(token_name($t[0]), ['T_WHITESPACE', 'T_COMMENT', 'T_DOC_COMMENT'], true)) {
                        continue;
                    }

                    $suivant = $t;
                    break;
                }

                // `if (…) { … }` se ferme par une accolade : seul `if (…):` nous concerne.
                if ($suivant === ':' && $nom !== 'T_ELSEIF') {
                    $pile[] = [$nom, $jeton[2]];
                }

                $i = $k;
            } elseif (in_array($nom, ['T_ENDIF', 'T_ENDFOREACH', 'T_ENDFOR', 'T_ENDWHILE', 'T_ENDSWITCH'], true)) {
                $dernier = array_pop($pile);

                if ($dernier === null) {
                    return sprintf('%s ligne %d ne ferme rien', $nom, $jeton[2]);
                }

                if ('T_END'.substr($dernier[0], 2) !== $nom) {
                    return sprintf(
                        '%s ligne %d ferme un %s ouvert ligne %d',
                        $nom, $jeton[2], $dernier[0], $dernier[1]
                    );
                }
            }
        }

        return $pile === [] ? null : sprintf(
            '%s ligne %d reste ouvert',
            $pile[0][0],
            $pile[0][1]
        );
    }

    private function relatif(string $chemin): string
    {
        return str_replace([base_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'], $chemin);
    }
}

<?php

namespace Tests\Feature\DesignSystem;

use Tests\TestCase;

/**
 * UNE CLASSE DU SYSTEME QU'UNE VUE ECRIT DOIT EXISTER EN CSS.
 *
 * Le composant `x-ui.badge` emettait `ui-badge-amber|green|blue|red` ; la feuille ne definit
 * que `ui-badge-neutral|brand|success|warning|danger|info`. Quatre tons sur cinq ne
 * correspondaient a AUCUNE regle — le badge sortait sans couleur, et le seul appel de
 * production portait justement `tone="blue"`.
 *
 * Rien ne pouvait le dire : ni le compilateur Blade, ni Tailwind, ni la suite. Une classe
 * absente ne casse pas, elle ne peint pas.
 *
 * LE SENS INVERSE (une classe definie que personne ne porte) n'est PAS teste ici : c'est du
 * poids mort, pas un defaut de rendu, et il se traite au nettoyage.
 */
class UneClasseEmiseDoitExisterTest extends TestCase
{
    /** Les prefixes qui appartiennent au systeme de design maison. */
    private const PREFIXES = ['brio-', 'ui-', 'cx-'];

    /**
     * Les classes composees a l'execution : leur nom complet n'existe dans aucune source.
     * Chacune est nommee avec l'endroit qui la compose, pour qu'on puisse verifier.
     *
     * @var array<string, string>
     */
    private const COMPOSEES = [
        // resources/views/components/toast.blade.php:64 — `brio-toast-${item.type}`
        'brio-toast-' => 'composee en JS depuis le type du message',
        // livewire/client/saved-payment-methods.blade.php:49 — `brio-carte-{{ $marque }}`
        'brio-carte-' => 'composee en Blade depuis la marque de la carte',
        // components/ui/stat.blade.php:80 — `'brio-kpi-trend-'.$sens`
        'brio-kpi-trend-' => 'composee en Blade depuis le sens de la tendance',
        // components/ui/confirmation.blade.php — `brio-modal-${ton}`
        'brio-modal-' => 'composee en Alpine depuis le ton de la modale',
    ];

    public function test_chaque_classe_du_systeme_ecrite_dans_une_vue_existe_en_css(): void
    {
        $definies = $this->classesDefiniesEnCss();
        $orphelines = [];

        foreach ($this->classesEcritesDansLesVues() as $classe => $sites) {
            if (isset($definies[$classe]) || $this->estComposee($classe)) {
                continue;
            }

            $orphelines[] = $classe.' ← '.implode(', ', array_slice($sites, 0, 2));
        }

        sort($orphelines);

        $this->assertSame([], $orphelines,
            'Une vue ecrit une classe du systeme que la feuille ne definit pas : elle ne peint rien.');
    }

    /**
     * TEMOIN. Sans lui, le test ci-dessus passerait au vert si l'extraction ne trouvait plus
     * aucune classe — il mesurerait alors sa propre panne.
     */
    public function test_temoin_l_extraction_voit_bien_les_classes(): void
    {
        $ecrites = $this->classesEcritesDansLesVues();
        $definies = $this->classesDefiniesEnCss();

        $this->assertGreaterThan(100, count($ecrites),
            'L\'extraction des vues ne trouve plus rien : le test ci-dessus ne prouverait plus rien.');

        $this->assertGreaterThan(100, count($definies),
            'L\'extraction du CSS ne trouve plus rien : tout serait declare orphelin.');

        // Une classe qu'on sait presente des DEUX cotes : le croisement fonctionne.
        $this->assertArrayHasKey('brio-glass', $definies);
    }

    /** @return array<string, true> */
    private function classesDefiniesEnCss(): array
    {
        $definies = [];

        foreach (glob(resource_path('css/*.css')) ?: [] as $f) {
            preg_match_all('/\.((?:brio|ui|cx)-[a-z0-9-]+)/', (string) file_get_contents($f), $m);

            foreach ($m[1] as $c) {
                $definies[$c] = true;
            }
        }

        return $definies;
    }

    /**
     * Les classes du systeme ecrites dans les vues, avec les fichiers qui les portent.
     *
     * @return array<string, list<string>>
     */
    private function classesEcritesDansLesVues(): array
    {
        $ecrites = [];
        $base = resource_path('views');

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));

        foreach ($it as $f) {
            if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.blade.php')) {
                continue;
            }

            $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($f->getPathname(), strlen($base) + 1));

            // `scribe/` est regeneree par `artisan scribe:generate` : son contenu n'est pas ecrit ici.
            if (str_starts_with($rel, 'scribe/')) {
                continue;
            }

            $source = (string) file_get_contents($f->getPathname());

            /*
             * ON NE LIT QUE LES VALEURS DE `class`. Le premier jet balayait le fichier entier
             * et ramenait `var(--brio-ink)`, `x-on:brio-confirmer`, `data-cx-globe-3d` — des
             * jetons, des evenements, des attributs de donnees, tout sauf des classes. Un
             * garde-fou qui crie a tort finit ignore.
             */
            preg_match_all('/(?<![:\w-])class="([^"]*)"/', $source, $m);

            // `@class([...])` compose aussi des classes, avec ses cles en litteral.
            preg_match_all('/@class\(\[(.*?)\]\)/s', $source, $mc);

            foreach (array_merge($m[1], $mc[1]) as $valeur) {
                // Un fragment interpole n'est pas un nom : `bg-{{ $c }}-100` n'existe nulle part.
                if (str_contains($valeur, '{{') || str_contains($valeur, '$')) {
                    $valeur = (string) preg_replace('/\S*(?:\{\{|\$)\S*/', ' ', $valeur);
                }

                preg_match_all('/(?:^|\s)((?:brio|ui|cx)-[a-z0-9-]+)(?=\s|$)/', $valeur, $mm);

                foreach ($mm[1] as $classe) {
                    $ecrites[$classe][] = $rel;
                }
            }
        }

        return array_map(fn (array $v): array => array_values(array_unique($v)), $ecrites);
    }

    private function estComposee(string $classe): bool
    {
        foreach (array_keys(self::COMPOSEES) as $racine) {
            if (str_starts_with($classe, $racine)) {
                return true;
            }
        }

        return false;
    }
}

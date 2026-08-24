<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * Un composant Blade doit etre monte quelque part.
 *
 * `x-rdv-cleaning-card` n'etait monte par aucune page — et il affichait le materiel fourni et les
 * photos jointes par le client, qu'AUCUN ecran routee ne montrait. Un composant sans appelant
 * n'est pas seulement du poids mort : il donne l'illusion qu'une information est presentee.
 */
class AucunComposantSansAppelantTest extends TestCase
{
    /**
     * CONSERVE EXPRES — le composant que les pages tierces attrapent quand elles cherchent « le
     * logo ». Son propre commentaire dit pourquoi : le laisser au SVG de Jetstream garantissait
     * qu'une page l'afficherait un jour sans que personne ne s'en apercoive.
     */
    private const VOULUS = [
        'application-mark',
    ];

    /**
     * DETTE MESUREE LE 2026-08-24, ni cachee ni corrigee : hors du lot en cours.
     *
     * Les trois `ui/` sont des primitives que la page `design-system` ne monte pas — elle ne
     * presente que badge, button, card, icon et stat. `welcome` est un reliquat d'installation.
     */
    private const DETTE = [
        'ui/page-header',
        'ui/skeleton',
        'ui/toast',
        'welcome',
    ];

    /** @return array<string, string> nom du composant => chemin de sa vue */
    private function composants(): array
    {
        $racine = str_replace(chr(92), '/', resource_path('views/components'));
        $trouves = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($racine)) as $fichier) {
            if (! $fichier->isFile() || ! str_ends_with($fichier->getFilename(), '.blade.php')) {
                continue;
            }

            $chemin = str_replace(chr(92), '/', $fichier->getPathname());
            $trouves[trim(str_replace([$racine, '.blade.php'], '', $chemin), '/')] = $chemin;
        }

        return $trouves;
    }

    /** @return array<string, string> chemin => contenu */
    private function sources(): array
    {
        $sources = [];

        foreach ([resource_path('views'), app_path()] as $dossier) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dossier)) as $fichier) {
                if (! $fichier->isFile() || ! str_ends_with($fichier->getFilename(), '.php')) {
                    continue;
                }

                $chemin = str_replace(chr(92), '/', $fichier->getPathname());
                $sources[$chemin] = (string) file_get_contents($chemin);
            }
        }

        return $sources;
    }

    /**
     * Les quatre facons de monter un composant : la balise, le nom de vue, et le nom pointe entre
     * quotes — celui que porte un attribut `#[Layout('layouts.app')]`.
     *
     * @param  array<string, string>  $sources
     */
    private function estMonte(string $nom, string $saPropreVue, array $sources): bool
    {
        $pointe = str_replace('/', '.', $nom);
        $formes = ['x-'.$pointe, 'components.'.$pointe, "'".$pointe."'", '"'.$pointe.'"'];

        foreach ($sources as $chemin => $source) {
            if ($chemin === $saPropreVue) {
                continue;
            }

            foreach ($formes as $forme) {
                if (str_contains($source, $forme)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** TEMOIN — le controle reconnait un montage, et ne s'invente pas d'appelant. */
    public function test_temoin_le_controle_distingue_monte_et_orphelin(): void
    {
        $sources = ['/ailleurs.blade.php' => '<x-ui.card title="x" />'];

        $this->assertTrue($this->estMonte('ui/card', '/sa-vue.blade.php', $sources));
        $this->assertFalse($this->estMonte('ui/table-shell', '/sa-vue.blade.php', $sources));

        // Une classe CSS `ui-card` ne monte AUCUN composant : le tiret n'est pas un point.
        $this->assertFalse($this->estMonte('ui/card', '/sa-vue.blade.php', ['/f.php' => 'class="ui-card"']));
    }

    public function test_aucun_composant_neuf_n_est_sans_appelant(): void
    {
        $composants = $this->composants();
        $sources = $this->sources();
        $connus = array_merge(self::VOULUS, self::DETTE);
        $orphelins = [];

        foreach ($composants as $nom => $chemin) {
            if (in_array($nom, $connus, true)) {
                continue;
            }

            if (! $this->estMonte($nom, $chemin, $sources)) {
                $orphelins[] = $nom;
            }
        }

        $this->assertGreaterThan(50, count($composants), 'Le balayage ne voit presque aucun composant.');
        $this->assertSame([], $orphelins, 'Ces composants ne sont montes nulle part : les brancher, ou les retirer.');
    }

    /** Une entree de dette qui a trouve son appelant doit SORTIR de la liste, sinon elle la fige. */
    public function test_la_dette_declaree_est_encore_exacte(): void
    {
        $composants = $this->composants();
        $sources = $this->sources();
        $resolus = [];

        foreach (array_merge(self::VOULUS, self::DETTE) as $nom) {
            if (isset($composants[$nom]) && $this->estMonte($nom, $composants[$nom], $sources)) {
                $resolus[] = $nom;
            }
        }

        $this->assertSame([], $resolus, 'Ces composants sont montes desormais : les retirer de la liste.');
    }
}

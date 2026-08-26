<?php

namespace Tests\Feature\DesignSystem;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UNE CLASSE DU SYSTEME SANS APPELANT EST UNE PROMESSE QUE PERSONNE NE TIENT.
 *
 * Trente-six l'etaient. La regle appliquee : on n'en garde une sans appelant que si son
 * ABSENCE serait un trou fonctionnel. Un seul cas y repond — voir plus bas.
 *
 * DEUX PIEGES DE MESURE, payes en route :
 *
 * 1. Une classe CITEE par une autre regle CSS — dans une liste `:is()` ou `:not()` — n'est
 *    pas orpheline : c'est un CONTRAT. `.brio-opaque` n'a aucun appelant et pourtant tout le
 *    verre en depend : `table:not(.brio-opaque)`. Dix classes sur trente-six etaient dans ce
 *    cas.
 *
 * 2. Une classe CONSTRUITE par concatenation echappe a une recherche litterale :
 *    `'brio-kpi-trend-'.$sens` et `brio-carte-{{ $marque }}` ne se trouvent pas en cherchant
 *    `brio-kpi-trend-up`.
 */
class LeSystemeNaPasDeClasseMorteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * CE QUI RESTE SANS APPELANT, ET POURQUOI.
     *
     * `.brio-status-dot-warning` complete un indicateur a TROIS etats dont les deux autres
     * sont employes (`-success`, `-danger`). Retirer l'etat du milieu obligerait le prochain
     * ecran qui en a besoin a fabriquer sa propre pastille ambre — c'est-a-dire a rouvrir
     * exactement le defaut qu'on vient de fermer.
     *
     * @var array<int, string>
     */
    private const TOLEREES = ['brio-status-dot-warning'];

    public function test_aucune_classe_du_systeme_n_est_morte(): void
    {
        $definies = [];

        foreach (glob(resource_path('css/*.css')) ?: [] as $feuille) {
            $contenu = (string) file_get_contents($feuille);

            // Une DEFINITION ouvre son propre bloc ; une simple citation, non.
            preg_match_all('/(^|,|\s)\.(brio-[a-z0-9-]+)[^{,]*\{/m', $contenu, $trouves);

            foreach ($trouves[2] as $classe) {
                $definies[$classe] = true;
            }
        }

        $employees = $this->classesEmployees();
        $citees = $this->citationsDansLeCss();
        $prefixes = $this->prefixesDynamiques();

        $mortes = [];

        foreach (array_keys($definies) as $classe) {
            if (isset($employees[$classe]) || in_array($classe, self::TOLEREES, true)) {
                continue;
            }

            // Citee ailleurs dans le CSS : c'est un contrat, pas un cadavre.
            if (($citees[$classe] ?? 0) > 1) {
                continue;
            }

            foreach ($prefixes as $prefixe) {
                if (str_starts_with($classe, $prefixe)) {
                    continue 2;
                }
            }

            $mortes[] = $classe;
        }

        sort($mortes);

        $this->assertSame([], $mortes, "Classes sans appelant :\n".implode("\n", $mortes));
    }

    /**
     * TEMOIN — le detecteur trouve VRAIMENT une classe morte quand il y en a une.
     *
     * Sans lui, une recherche trop large (comptant les citations comme des appels) rendrait
     * un tableau vide en permanence : le test passerait en ne mesurant rien.
     */
    public function test_temoin_le_detecteur_reconnait_une_classe_morte(): void
    {
        $employees = $this->classesEmployees();

        $this->assertArrayNotHasKey('brio-classe-qui-n-existe-pas', $employees);

        // Et le controle inverse : une classe bien employee est reconnue.
        $this->assertArrayHasKey('brio-alerte-success', $employees, 'Les bannieres de session l\'emploient.');
        $this->assertArrayHasKey('brio-stat-unit', $employees, 'L\'ecran de l\'employe l\'emploie.');
    }

    /** TEMOIN — la classe toleree est bien celle qu'on croit, et ses soeurs sont employees. */
    public function test_temoin_la_seule_toleree_complete_une_famille_vivante(): void
    {
        $employees = $this->classesEmployees();

        $this->assertArrayHasKey('brio-status-dot-success', $employees);
        $this->assertArrayHasKey('brio-status-dot-danger', $employees);
        $this->assertArrayNotHasKey('brio-status-dot-warning', $employees);
    }

    /** @return array<string, bool> */
    private function classesEmployees(): array
    {
        $trouvees = [];

        foreach ($this->fichiers() as $chemin) {
            preg_match_all('/brio-[a-z0-9-]+/', (string) file_get_contents($chemin), $m);

            foreach ($m[0] as $classe) {
                $trouvees[$classe] = true;
            }
        }

        return $trouvees;
    }

    /** @return array<string, int> */
    private function citationsDansLeCss(): array
    {
        $compte = [];

        foreach (glob(resource_path('css/*.css')) ?: [] as $feuille) {
            preg_match_all('/\.(brio-[a-z0-9-]+)/', (string) file_get_contents($feuille), $m);

            foreach ($m[1] as $classe) {
                $compte[$classe] = ($compte[$classe] ?? 0) + 1;
            }
        }

        return $compte;
    }

    /** @return array<int, string> */
    private function prefixesDynamiques(): array
    {
        $prefixes = [];

        foreach ($this->fichiers() as $chemin) {
            preg_match_all(
                '/(brio-[a-z0-9-]*-)(?:\{\{|\$\{|\'|")/',
                (string) file_get_contents($chemin),
                $m
            );

            foreach ($m[1] as $prefixe) {
                $prefixes[] = $prefixe;
            }
        }

        return array_unique($prefixes);
    }

    /** @return array<int, string> */
    private function fichiers(): array
    {
        $trouves = [];

        foreach ([resource_path('views'), resource_path('js'), app_path()] as $racine) {
            $iterateur = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($racine, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterateur as $fichier) {
                if (! $fichier->isFile()) {
                    continue;
                }

                $nom = $fichier->getFilename();

                if (str_ends_with($nom, '.blade.php') || str_ends_with($nom, '.js') || str_ends_with($nom, '.php')) {
                    $trouves[] = $fichier->getPathname();
                }
            }
        }

        return $trouves;
    }
}

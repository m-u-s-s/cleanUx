<?php

namespace Tests\Feature\Devops;

use App\Models\MissionAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * SUPPRIMER UNE COLONNE NE SUFFIT PAS — IL FAUT AUSSI CESSER DE LA NOMMER.
 *
 * CE QUI S'EST PASSÉ, ET QUI JUSTIFIE CE FICHIER. Les colonnes dormantes `role` et `status` de
 * `mission_assignments` ont été retirées le 2026-09-01. La vérification préalable a couvert `app/`,
 * les vues, les fabriques et les semeurs — et PAS `tests/`. Cent seize tests sont tombés d'un coup,
 * tous sur le même `MassAssignmentException`, et le rouge n'est apparu qu'à la suite complète,
 * plusieurs poussées plus tard.
 *
 * PIRE QUE LES TESTS : `OfferStatsService` sélectionnait encore `status` dans une liste de colonnes
 * EXPLICITE. Cela ne casse aucun test tant qu'aucun test n'atteint la requête — et aucun ne
 * l'atteignait, l'exception de masse survenant à la création. En production, MySQL aurait refusé la
 * requête : l'écran des statistiques d'offres du prestataire répondait 500.
 *
 * ── POURQUOI UN TEST PLUTÔT QU'UNE RELECTURE ────────────────────────────────────────────────
 *
 * Parce que la relecture a déjà échoué, et pour une raison structurelle : une colonne retirée ne
 * laisse aucune trace là où on la cherche. `grep` sur `'status'` rend des centaines de résultats
 * dont l'immense majorité concerne d'autres tables — l'œil s'y noie, et c'est exactement ce qui
 * est arrivé. Ce test ne regarde que `mission_assignments`, et il ne se fatigue pas.
 *
 * Ce fichier ne défend PAS le fait que ces colonnes soient supprimées : c'est le rôle de la
 * migration. Il défend le fait que plus personne ne les nomme.
 */
class UneColonneSupprimeeNestPlusNommeeTest extends TestCase
{
    use RefreshDatabase;

    /** Les colonnes retirées de `mission_assignments`, et ce qui les remplace. */
    private const RETIREES = [
        'role' => 'role_on_mission',
        'status' => 'assignment_status',
    ];

    public function test_les_colonnes_dormantes_sont_bien_absentes_du_schema(): void
    {
        foreach (array_keys(self::RETIREES) as $morte) {
            $this->assertFalse(
                Schema::hasColumn('mission_assignments', $morte),
                "La colonne « {$morte} » est revenue. Elle répondait à une question déjà posée par "
                .'une autre colonne, et se remplissait toute seule d’un défaut qui ne variait '
                .'jamais — un tableau de bord qui la lirait verrait toutes les offres figées.',
            );
        }
    }

    /**
     * TÉMOIN — les colonnes VIVANTES, elles, sont bien là.
     *
     * Sans lui, le test précédent passerait au vert sur une table absente, mal nommée, ou sur un
     * `Schema::hasColumn` qui rendrait `false` pour une raison étrangère au sujet. C'est le
     * contrôle positif qu'exige tout test d'absence : prouver que la mesure sait dire « présent ».
     */
    public function test_temoin_les_colonnes_vivantes_repondent_presentes(): void
    {
        foreach (self::RETIREES as $vivante) {
            $this->assertTrue(
                Schema::hasColumn('mission_assignments', $vivante),
                "« {$vivante} » manque : la mesure ne prouve plus rien.",
            );
        }
    }

    /**
     * AUCUN FICHIER NE DOIT PLUS ÉCRIRE NI SÉLECTIONNER CES COLONNES.
     *
     * On cherche les deux formes qui ont réellement mordu : l'affectation en masse
     * (`'status' => …`) et la liste de colonnes explicite (`'status',` dans un `get([...])`), à
     * l'intérieur d'un bloc qui parle de `mission_assignments`.
     */
    public function test_aucune_source_ne_nomme_plus_les_colonnes_retirees(): void
    {
        $coupables = [];

        foreach ($this->fichiersQuiParlentDAffectations() as $chemin => $source) {
            foreach ($this->blocsPortantSurUneAffectation($source) as $bloc) {
                foreach (self::RETIREES as $morte => $vivante) {
                    if (preg_match("/'{$morte}'\s*(=>|,)/", $bloc) !== 1) {
                        continue;
                    }

                    $coupables[] = "{$chemin} nomme « {$morte} » sur une affectation — la colonne "
                        ."n’existe plus, employer « {$vivante} ».";
                }
            }
        }

        $this->assertSame([], array_values(array_unique($coupables)), implode("\n", array_unique($coupables)));
    }

    /**
     * TÉMOIN DE PORTÉE — la recherche voit bien des fichiers.
     *
     * Sans lui, le test précédent serait vert sur un chemin de base faux, un glob qui ne rend rien,
     * ou une extension mal filtrée : il compterait zéro coupable parmi zéro fichier lu.
     */
    public function test_temoin_la_recherche_lit_bien_des_fichiers(): void
    {
        $fichiers = $this->fichiersQuiParlentDAffectations();

        $this->assertGreaterThan(20, count($fichiers));

        /*
         * ET LA DÉTECTION MORD QUAND ELLE DOIT — éprouvée sur deux sources fabriquées, une
         * fautive et une innocente. C'est le vrai contrôle positif de ce fichier : sans lui, un
         * découpage de blocs qui ne rendrait jamais rien laisserait tout passer en silence.
         */
        $fautive = <<<'PHP'
            MissionAssignment::query()->create(['assignment_status' => 'accepted', 'status' => 'accepted']);
            PHP;

        $innocente = <<<'PHP'
            Mission::query()->create(['status' => 'completed']);
            $x = MissionAssignment::query()->where('assignment_status', 'accepted')->get();
            PHP;

        $detecte = function (string $source): bool {
            foreach ($this->blocsPortantSurUneAffectation($source) as $bloc) {
                if (preg_match("/'status'\s*(=>|,)/", $bloc) === 1) {
                    return true;
                }
            }

            return false;
        };

        $this->assertTrue($detecte($fautive), 'La détection ne voit plus la forme qui a réellement mordu.');
        $this->assertFalse($detecte($innocente), 'La détection accuse le statut d’une MISSION : elle crierait au loup.');
    }

    // ─────────────────────────────────────────────────────────────────────

    /**
     * LES BLOCS QUI PORTENT VRAIMENT SUR UNE AFFECTATION, et rien d'autre.
     *
     * Chercher `'status'` dans un fichier entier ne vaut rien : la première version de ce test
     * accusait douze fichiers innocents, qui parlaient du statut d'une MISSION ou d'une
     * RÉSERVATION et mentionnaient `assignment_status` ailleurs. Un garde-fou qui crie au loup
     * finit désactivé, ce qui est pire que pas de garde-fou du tout.
     *
     * On isole donc l'appel : depuis `MissionAssignment::…(` ou `assignments()->…(`, on avance
     * jusqu'à la parenthèse fermante correspondante, et on ne lit que l'intérieur. C'est
     * exactement le périmètre où les deux formes fautives se sont trouvées — l'affectation en
     * masse et la liste de colonnes explicite.
     *
     * @return list<string>
     */
    private function blocsPortantSurUneAffectation(string $source): array
    {
        $debuts = '/(MissionAssignment::(?:query\(\)->)?[a-zA-Z]+\s*\(|assignments\(\)->[a-zA-Z]+\s*\()/';

        if (preg_match_all($debuts, $source, $trouvailles, PREG_OFFSET_CAPTURE) === 0) {
            return [];
        }

        $blocs = [];

        foreach ($trouvailles[0] as [$texte, $position]) {
            $i = $position + strlen($texte) - 1;   // sur la parenthèse ouvrante
            $profondeur = 0;

            for ($j = $i, $n = strlen($source); $j < $n; $j++) {
                if ($source[$j] === '(') {
                    $profondeur++;
                } elseif ($source[$j] === ')') {
                    $profondeur--;

                    if ($profondeur === 0) {
                        break;
                    }
                }
            }

            $blocs[] = substr($source, $i, $j - $i);
        }

        return $blocs;
    }

    /** @return array<string, string> chemin relatif => source */
    private function fichiersQuiParlentDAffectations(): array
    {
        $trouves = [];

        foreach (['app', 'tests', 'database', 'routes'] as $racine) {
            $iterateur = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($racine), \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterateur as $fichier) {
                if ($fichier->getExtension() !== 'php') {
                    continue;
                }

                $source = (string) file_get_contents($fichier->getPathname());

                if (! str_contains($source, 'MissionAssignment') && ! str_contains($source, 'mission_assignments')) {
                    continue;
                }

                // Ce fichier-ci nomme les colonnes retirées : c'est son sujet.
                if (str_contains($source, self::class) || str_ends_with($fichier->getPathname(), basename(__FILE__))) {
                    continue;
                }

                $trouves[str_replace(base_path().DIRECTORY_SEPARATOR, '', $fichier->getPathname())] = $source;
            }
        }

        return $trouves;
    }

    /**
     * ET LA TABLE FONCTIONNE ENCORE — une affectation se crée et se relit.
     *
     * Le témoin le plus large du fichier : si le retrait des colonnes avait cassé l'écriture, tout
     * ce qui précède resterait vert en mesurant une table devenue inutilisable.
     */
    public function test_temoin_une_affectation_se_cree_toujours(): void
    {
        $affectation = MissionAssignment::factory()->create();

        $this->assertNotNull($affectation->refresh()->assignment_status);
    }
}

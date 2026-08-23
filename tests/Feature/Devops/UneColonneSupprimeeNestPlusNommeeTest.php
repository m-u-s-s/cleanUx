<?php

namespace Tests\Feature\Devops;

use App\Models\MissionAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** SUPPRIMER UNE COLONNE NE SUFFIT PAS — IL FAUT AUSSI CESSER DE LA NOMMER. */
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

    /** TÉMOIN — les colonnes VIVANTES, elles, sont bien là. */
    public function test_temoin_les_colonnes_vivantes_repondent_presentes(): void
    {
        $absentes = array_values(array_filter(
            self::RETIREES,
            fn (string $c) => ! Schema::hasColumn('mission_assignments', $c),
        ));

        $this->assertSame([], $absentes, 'Ces colonnes manquent : la mesure ne prouverait plus rien.');
    }

    /** AUCUN FICHIER NE DOIT PLUS ÉCRIRE NI SÉLECTIONNER CES COLONNES. */
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

    /** TÉMOIN DE PORTÉE — la recherche voit bien des fichiers. */
    public function test_temoin_la_recherche_lit_bien_des_fichiers(): void
    {
        $fichiers = $this->fichiersQuiParlentDAffectations();

        $this->assertGreaterThan(20, count($fichiers));

        // ET LA DÉTECTION MORD QUAND ELLE DOIT — éprouvée sur deux sources fabriquées, une fautive et une innocente.
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

    /** ET LA TABLE FONCTIONNE ENCORE — une affectation se crée et se relit. */
    public function test_temoin_une_affectation_se_cree_toujours(): void
    {
        $affectation = MissionAssignment::factory()->create();

        $this->assertNotNull($affectation->refresh()->assignment_status);
    }
}

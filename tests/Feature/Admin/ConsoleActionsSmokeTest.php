<?php

namespace Tests\Feature\Admin;

use App\Admin\Console\ResourceRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Toute action déclarée est ATTEIGNABLE et bien formée.
 *
 * POURQUOI CE TEST GÉNÉRIQUE PLUTÔT QU'UN TEST PAR ACTION. Quatre-vingts modules porteront bientôt
 * plusieurs centaines de gestes. Écrire un test métier pour chacun est le travail de chaque domaine ;
 * ce fichier tient la garantie STRUCTURELLE que ces tests-là ne donnent pas — qu'une action déclarée
 * porte une clé utilisable, un libellé lisible, et des champs valides quand elle en exige.
 *
 * CE QU'IL ATTRAPE, et que rien d'autre n'attrape : une action au libellé vide qui rendrait un
 * bouton anonyme, une clé en doublon dont une seule serait atteignable, un champ requis sans règle
 * qui accepterait n'importe quoi. Trois défauts qui ne cassent rien à la compilation, ne lèvent
 * aucune erreur, et ne se voient qu'au moment où quelqu'un appuie.
 */
class ConsoleActionsSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'platform_role' => 'admin']));
    }

    public function test_chaque_action_est_bien_formee(): void
    {
        $fautes = [];

        foreach (app(ResourceRegistry::class)->keys() as $cle) {
            $descripteur = app(ResourceRegistry::class)->for($cle);

            if (! $descripteur) {
                continue;
            }

            $vues = [];

            foreach ([...$descripteur->actions(), ...$descripteur->globalActions()] as $action) {
                $spec = $action->toArray();

                if (($spec['key'] ?? '') === '') {
                    $fautes[] = "{$cle} : une action sans clé";

                    continue;
                }

                if (trim((string) ($spec['label'] ?? '')) === '') {
                    $fautes[] = "{$cle}.{$spec['key']} : libellé vide — le bouton serait anonyme";
                }

                if (in_array($spec['key'], $vues, true)) {
                    // Deux actions de même clé : une seule serait atteignable, et rien ne dirait
                    // laquelle.
                    $fautes[] = "{$cle}.{$spec['key']} : clé en doublon";
                }

                $vues[] = $spec['key'];

                foreach ($spec['fields'] ?? [] as $champ) {
                    if (($champ['key'] ?? '') === '') {
                        $fautes[] = "{$cle}.{$spec['key']} : un champ requis sans clé";
                    }
                }
            }
        }

        $this->assertSame([], $fautes, implode("\n", $fautes));
    }

    public function test_chaque_champ_exige_par_une_action_porte_ses_regles(): void
    {
        $sansRegle = [];

        foreach (app(ResourceRegistry::class)->keys() as $cle) {
            $descripteur = app(ResourceRegistry::class)->for($cle);

            foreach ([...($descripteur?->actions() ?? []), ...($descripteur?->globalActions() ?? [])] as $action) {
                foreach ($action->fields() as $champ) {
                    // Un champ sans règle accepte n'importe quoi : c'est le serveur qui valide,
                    // le mobile ne connaît que le type et le caractère obligatoire.
                    if ($champ->validationRules() === []) {
                        $sansRegle[] = $cle.'.'.$action->toArray()['key'].'.'.$champ->key();
                    }
                }
            }
        }

        $this->assertSame([], $sansRegle, implode("\n", $sansRegle));
    }

    public function test_le_balayage_couvre_bien_les_descripteurs(): void
    {
        // Un balayage qui ne trouverait plus rien rendrait les deux assertions ci-dessus vraies
        // pour la pire des raisons.
        $actions = 0;

        foreach (app(ResourceRegistry::class)->keys() as $cle) {
            $d = app(ResourceRegistry::class)->for($cle);
            $actions += count($d?->actions() ?? []) + count($d?->globalActions() ?? []);
        }

        $this->assertGreaterThan(20, $actions, 'Le balayage ne voit plus d’actions : il a cessé de balayer.');
    }
}

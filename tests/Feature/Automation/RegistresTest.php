<?php

namespace Tests\Feature\Automation;

use App\Services\Automation\Registre\ActionRegistre;
use App\Services\Automation\Registre\DeclencheurRegistre;
use App\Services\Automation\Registre\EntiteRegistre;
use App\Services\Conditions\RuleTreeEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Ce qui doit rester vrai de TOUTE action et de TOUTE entite, y compris celles a venir. */
class RegistresTest extends TestCase
{
    use RefreshDatabase;

    public function test_chaque_action_declare_une_cle_un_libelle_et_des_entites(): void
    {
        $ecarts = [];

        foreach (app(ActionRegistre::class)->toutes() as $cle => $action) {
            if ($action->cle() !== $cle) {
                $ecarts[] = "{$cle} : la cle du registre ne correspond pas a cle()";
            }
            if (trim($action->libelle()) === '') {
                $ecarts[] = "{$cle} : libelle vide";
            }
            if ($action->entitesSupportees() === []) {
                $ecarts[] = "{$cle} : aucune entite supportee";
            }
        }

        $this->assertSame([], $ecarts, implode("\n", $ecarts));
    }

    public function test_chaque_action_ne_supporte_que_des_entites_enregistrees(): void
    {
        $connues = app(EntiteRegistre::class)->cles();
        $ecarts = [];

        foreach (app(ActionRegistre::class)->toutes() as $cle => $action) {
            foreach ($action->entitesSupportees() as $entite) {
                if (! in_array($entite, $connues, true)) {
                    $ecarts[] = "{$cle} : entite inconnue « {$entite} »";
                }
            }
        }

        $this->assertSame([], $ecarts, implode("\n", $ecarts));
    }

    public function test_chaque_entite_n_expose_que_des_operateurs_connus(): void
    {
        $ecarts = [];

        foreach (app(EntiteRegistre::class)->cles() as $cle) {
            foreach (app(EntiteRegistre::class)->descripteur($cle)->operators() as $op) {
                if (! in_array($op, RuleTreeEvaluator::OPERATEURS_CONNUS, true)) {
                    $ecarts[] = "{$cle} : operateur inconnu « {$op} »";
                }
            }
        }

        $this->assertSame([], $ecarts, implode("\n", $ecarts));
    }

    /** TEMOIN — les deux registres ne sont pas vides. Sans lui, les trois tests ci-dessus
     *  passeraient au vert sur des registres sans rien dedans. */
    public function test_temoin_les_deux_registres_portent_quelque_chose(): void
    {
        $this->assertNotEmpty(app(ActionRegistre::class)->toutes());
        $this->assertNotEmpty(app(EntiteRegistre::class)->cles());
    }

    public function test_chaque_declencheur_declare_cle_libelle_evenement_et_entite_valides(): void
    {
        $ecarts = [];
        $entitesConnues = app(EntiteRegistre::class)->cles();

        foreach (app(DeclencheurRegistre::class)->toutes() as $cle => $declencheur) {
            if ($declencheur->cle() !== $cle) {
                $ecarts[] = "{$cle} : la cle du registre ne correspond pas a cle()";
            }
            if (trim($declencheur->libelle()) === '') {
                $ecarts[] = "{$cle} : libelle vide";
            }
            if (! class_exists($declencheur->evenement())) {
                $ecarts[] = "{$cle} : classe d'evenement inexistante « {$declencheur->evenement()} »";
            }
            if (! in_array($declencheur->entite(), $entitesConnues, true)) {
                $ecarts[] = "{$cle} : entite inconnue « {$declencheur->entite()} »";
            }
        }

        $this->assertSame([], $ecarts, implode("\n", $ecarts));
    }

    /** TEMOIN — le registre des declencheurs n'est pas vide. Sans lui, le test ci-dessus
     *  passerait au vert sur un registre sans rien dedans. */
    public function test_temoin_le_registre_des_declencheurs_porte_quelque_chose(): void
    {
        $this->assertNotEmpty(app(DeclencheurRegistre::class)->toutes());
    }

    /** LA CONVENTION EST LA GARDE. Un nom non qualifie echappe au test d'execution : sur
     *  SQLite un identifiant inconnu devient une chaine litterale au lieu de lever. */
    public function test_chaque_champ_nomme_sa_table(): void
    {
        $ecarts = [];

        foreach (app(EntiteRegistre::class)->cles() as $cle) {
            foreach (app(EntiteRegistre::class)->descripteur($cle)->fields() as $champ => $liaison) {
                if ($liaison->colonne !== null && ! str_contains($liaison->colonne, '.')) {
                    $ecarts[] = "{$cle}.{$champ} : « {$liaison->colonne} » ne nomme pas sa table";
                }
            }
        }

        $this->assertSame([], $ecarts, implode("\n", $ecarts));
    }
}

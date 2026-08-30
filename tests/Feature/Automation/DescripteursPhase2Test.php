<?php

namespace Tests\Feature\Automation;

use App\Models\AlerteMetier;
use App\Models\Mission;
use App\Services\Automation\Registre\EntiteRegistre;
use App\Services\Conditions\RuleTreeEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DescripteursPhase2Test extends TestCase
{
    use RefreshDatabase;

    public function test_les_deux_entites_sont_enregistrees(): void
    {
        $cles = app(EntiteRegistre::class)->cles();

        $this->assertContains('alerte', $cles);
        $this->assertContains('mission', $cles);
    }

    /** CHAQUE CHAMP DECLARE DOIT S'EXECUTER. Un champ qui nomme une colonne absente ne se
     *  voit qu'a l'execution : SQLite prend un identifiant inconnu pour une chaine litterale. */
    public function test_chaque_champ_declare_s_execute_vraiment(): void
    {
        $registre = app(EntiteRegistre::class);
        $ecarts = [];

        foreach (['alerte', 'mission'] as $cle) {
            $descripteur = $registre->descripteur($cle);

            foreach (array_keys($descripteur->fields()) as $champ) {
                try {
                    $requete = $descripteur->baseQuery();
                    app(RuleTreeEvaluator::class)->apply(
                        $requete,
                        ['field' => $champ, 'op' => 'is_not_null', 'value' => null],
                        $descripteur
                    );
                    $requete->limit(1)->get();
                } catch (\Throwable $e) {
                    $ecarts[] = "{$cle}.{$champ} : ".$e->getMessage();
                }
            }
        }

        $this->assertSame([], $ecarts, implode("\n", $ecarts));
    }

    public function test_une_condition_sur_la_cle_d_alerte_selectionne(): void
    {
        AlerteMetier::create(['cle' => 'payout_failed', 'niveau' => 'critical', 'message' => 'a', 'levee_le' => now()]);
        AlerteMetier::create(['cle' => 'webhook_backlog', 'niveau' => 'critical', 'message' => 'b', 'levee_le' => now()]);

        $descripteur = app(EntiteRegistre::class)->descripteur('alerte');
        $requete = $descripteur->baseQuery();

        app(RuleTreeEvaluator::class)->apply(
            $requete,
            ['field' => 'cle', 'op' => 'eq', 'value' => 'payout_failed'],
            $descripteur
        );

        $this->assertSame(1, $requete->count());
    }

    /** GARDE (relecture) — `prestataire_id` a ete retire : Mission::intervenantId() fait
     *  autorite en coalescant lead_provider_user_id et lead_employee_id, et FieldBinding ne sait
     *  lier qu'une seule colonne. Un champ borne a une seule des deux mentirait a moitie. */
    public function test_qui_intervient_ne_s_expose_pas_a_moitie_sur_une_seule_colonne(): void
    {
        Mission::factory()->create(['lead_provider_user_id' => 1, 'lead_employee_id' => null]);
        Mission::factory()->create(['lead_provider_user_id' => null, 'lead_employee_id' => 2]);

        $descripteur = app(EntiteRegistre::class)->descripteur('mission');
        $requete = $descripteur->baseQuery();

        app(RuleTreeEvaluator::class)->apply(
            $requete,
            ['field' => 'prestataire_id', 'op' => 'is_not_null', 'value' => null],
            $descripteur
        );

        $this->assertSame(0, $requete->count());
    }

    /** TEMOIN — les deux missions du test precedent sont bien la ; sans lui, le 0 ci-dessus
     *  passerait au vert sur une table vide plutot que sur l'absence reelle du champ. */
    public function test_temoin_les_deux_missions_du_garde_prestataire_existent(): void
    {
        Mission::factory()->create(['lead_provider_user_id' => 1, 'lead_employee_id' => null]);
        Mission::factory()->create(['lead_provider_user_id' => null, 'lead_employee_id' => 2]);

        $this->assertSame(2, app(EntiteRegistre::class)->descripteur('mission')->baseQuery()->count());
    }

    /** TEMOIN — sans condition, les deux alertes sont bien la. Sans lui, le test ci-dessus
     *  passerait au vert sur une requete qui ne rend jamais rien. */
    public function test_temoin_la_requete_de_base_voit_les_deux_alertes(): void
    {
        AlerteMetier::create(['cle' => 'payout_failed', 'niveau' => 'critical', 'message' => 'a', 'levee_le' => now()]);
        AlerteMetier::create(['cle' => 'webhook_backlog', 'niveau' => 'critical', 'message' => 'b', 'levee_le' => now()]);

        $this->assertSame(2, app(EntiteRegistre::class)->descripteur('alerte')->baseQuery()->count());
    }
}

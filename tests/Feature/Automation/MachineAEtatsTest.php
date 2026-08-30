<?php

namespace Tests\Feature\Automation;

use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\Booking;
use App\Services\Automation\ArmementRefuse;
use App\Services\Automation\EtatDeRegle;
use App\Services\Automation\RuleRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MachineAEtatsTest extends TestCase
{
    use RefreshDatabase;

    private function regle(): AutomationRule
    {
        return AutomationRule::create([
            'nom' => 'Les réservations en attente',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'cadence' => 'quart_heure',
            'conditions' => ['field' => 'statut', 'op' => 'eq', 'value' => 'en_attente'],
            'actions' => [['cle' => 'journaliser', 'parametres' => ['message' => 'vue']]],
        ]);
    }

    public function test_armer_une_regle_au_journal_vide_est_refuse(): void
    {
        $regle = $this->regle();
        app(EtatDeRegle::class)->observer($regle);

        $this->expectException(ArmementRefuse::class);

        app(EtatDeRegle::class)->armer($regle);
    }

    /**
     * TEMOIN — la meme regle s'arme des qu'elle a observe quelque chose. Sans lui, le refus
     * ci-dessus passerait au vert sur un armement casse pour tout le monde.
     */
    public function test_temoin_apres_un_passage_d_observation_l_armement_passe(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);

        $regle = $this->regle();
        app(EtatDeRegle::class)->observer($regle);
        app(RuleRunner::class)->executer($regle->fresh());

        app(EtatDeRegle::class)->armer($regle->fresh());

        $this->assertSame(AutomationRule::ETAT_ARMEE, $regle->fresh()->etat);
    }

    public function test_un_passage_d_observation_sans_correspondance_ne_suffit_pas(): void
    {
        // Aucune reservation : le passage n'ecrit aucune ligne, donc le journal reste vide.
        $regle = $this->regle();
        app(EtatDeRegle::class)->observer($regle);
        app(RuleRunner::class)->executer($regle->fresh());

        $this->expectException(ArmementRefuse::class);

        app(EtatDeRegle::class)->armer($regle->fresh());
    }

    public function test_suspendre_et_desactiver_posent_l_etat(): void
    {
        $regle = $this->regle();

        app(EtatDeRegle::class)->suspendre($regle, 'emballement');
        $this->assertSame(AutomationRule::ETAT_SUSPENDUE, $regle->fresh()->etat);

        app(EtatDeRegle::class)->desactiver($regle->fresh());
        $this->assertSame(AutomationRule::ETAT_DESACTIVEE, $regle->fresh()->etat);
    }

    public function test_une_regle_dont_le_journal_n_est_qu_en_mode_arme_reste_inarmable(): void
    {
        $regleA = $this->regle();

        AutomationAction::create([
            'automation_rule_id' => $regleA->id,
            'entite_type' => 'Booking',
            'entite_id' => 1,
            'mode' => 'armee',
            'action_cle' => 'journaliser',
            'resultat' => 'executee',
            'pose_le' => now(),
        ]);

        $this->expectException(ArmementRefuse::class);

        app(EtatDeRegle::class)->armer($regleA);
    }

    public function test_l_observation_d_une_autre_regle_ne_rend_pas_la_premiere_armable(): void
    {
        $regleA = $this->regle();
        $regleB = $this->regle();
        $regleB->update(['nom' => 'Une autre règle']);

        AutomationAction::create([
            'automation_rule_id' => $regleA->id,
            'entite_type' => 'Booking',
            'entite_id' => 1,
            'mode' => 'armee',
            'action_cle' => 'journaliser',
            'resultat' => 'executee',
            'pose_le' => now(),
        ]);

        AutomationAction::create([
            'automation_rule_id' => $regleB->id,
            'entite_type' => 'Booking',
            'entite_id' => 2,
            'mode' => 'observation',
            'action_cle' => 'journaliser',
            'resultat' => 'simulee',
            'pose_le' => now(),
        ]);

        $this->expectException(ArmementRefuse::class);

        app(EtatDeRegle::class)->armer($regleA);
    }

    /** DEFAUT A3 — un journal d'observation entierement en echec ne suffit pas a armer. */
    public function test_une_observation_entierement_en_echec_ne_suffit_pas_a_armer(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);

        $regle = AutomationRule::create([
            'nom' => 'Action inconnue',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'cadence' => 'quart_heure',
            'conditions' => ['field' => 'statut', 'op' => 'eq', 'value' => 'en_attente'],
            'actions' => [['cle' => 'action_qui_n_existe_pas', 'parametres' => []]],
        ]);

        app(EtatDeRegle::class)->observer($regle);
        app(RuleRunner::class)->executer($regle->fresh());

        $this->expectException(ArmementRefuse::class);

        app(EtatDeRegle::class)->armer($regle->fresh());
    }

    public function test_temoin_la_regle_qui_a_observe_s_arme(): void
    {
        $regleB = $this->regle();

        AutomationAction::create([
            'automation_rule_id' => $regleB->id,
            'entite_type' => 'Booking',
            'entite_id' => 2,
            'mode' => 'observation',
            'action_cle' => 'journaliser',
            'resultat' => 'simulee',
            'pose_le' => now(),
        ]);

        app(EtatDeRegle::class)->armer($regleB);

        $this->assertSame(AutomationRule::ETAT_ARMEE, $regleB->fresh()->etat);
    }

    public function test_chaque_transition_est_journalisee(): void
    {
        $regle = $this->regle();

        app(EtatDeRegle::class)->observer($regle);
        $this->assertDatabaseHas('activity_logs', ['action' => 'automation.regle_observation']);

        Booking::factory()->create(['status' => 'en_attente']);
        app(RuleRunner::class)->executer($regle->fresh());

        app(EtatDeRegle::class)->armer($regle->fresh());
        $this->assertDatabaseHas('activity_logs', ['action' => 'automation.regle_armee']);

        app(EtatDeRegle::class)->suspendre($regle->fresh(), 'test');
        $this->assertDatabaseHas('activity_logs', ['action' => 'automation.regle_suspendue']);

        app(EtatDeRegle::class)->desactiver($regle->fresh());
        $this->assertDatabaseHas('activity_logs', ['action' => 'automation.regle_desactivee']);
    }
}

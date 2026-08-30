<?php

namespace Tests\Feature\Automation;

use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\Booking;
use App\Services\Automation\RuleRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RuleRunnerTest extends TestCase
{
    use RefreshDatabase;

    private function regle(string $etat, array $attributs = []): AutomationRule
    {
        return AutomationRule::create(array_merge([
            'nom' => 'Les réservations en attente',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'cadence' => 'quart_heure',
            'conditions' => ['field' => 'statut', 'op' => 'eq', 'value' => 'en_attente'],
            'actions' => [['cle' => 'journaliser', 'parametres' => ['message' => 'vue']]],
            'etat' => $etat,
        ], $attributs));
    }

    public function test_en_observation_la_regle_journalise_et_n_agit_pas(): void
    {
        Booking::factory()->count(2)->create(['status' => 'en_attente']);
        Booking::factory()->create(['status' => 'confirme']);   // TEMOIN : hors conditions

        $passage = app(RuleRunner::class)->executer($this->regle(AutomationRule::ETAT_OBSERVATION));

        $this->assertSame('observation', $passage->mode);
        $this->assertSame(2, $passage->entites_vues);
        $this->assertSame(2, AutomationAction::where('resultat', 'simulee')->count());
        // L'observation n'ecrit RIEN dans le journal d'activite.
        $this->assertDatabaseMissing('activity_logs', ['action' => 'automation.note']);
    }

    /** TEMOIN de l'observation — armee, la meme regle ecrit vraiment. */
    public function test_temoin_armee_la_meme_regle_agit(): void
    {
        Booking::factory()->count(2)->create(['status' => 'en_attente']);

        $passage = app(RuleRunner::class)->executer($this->regle(AutomationRule::ETAT_ARMEE));

        $this->assertSame('armee', $passage->mode);
        $this->assertSame(2, AutomationAction::where('resultat', 'executee')->count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'automation.note']);
    }

    public function test_une_action_qui_echoue_n_emporte_pas_le_passage(): void
    {
        Notification::fake();

        // Aucun administrateur : `notifier.admins` echoue pour chaque entite.
        Booking::factory()->count(3)->create(['status' => 'en_attente']);

        $regle = $this->regle(AutomationRule::ETAT_ARMEE, [
            'actions' => [['cle' => 'notifier.admins', 'parametres' => ['message' => 'x']]],
        ]);

        $passage = app(RuleRunner::class)->executer($regle);

        $this->assertSame(3, $passage->entites_vues);
        $this->assertSame(3, AutomationAction::where('resultat', 'echouee')->count());
    }

    public function test_une_action_inconnue_est_journalisee_en_echec(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);

        $regle = $this->regle(AutomationRule::ETAT_ARMEE, [
            'actions' => [['cle' => 'action.qui.n.existe.pas', 'parametres' => []]],
        ]);

        app(RuleRunner::class)->executer($regle);

        $this->assertSame(1, AutomationAction::where('resultat', 'echouee')->count());
    }

    public function test_restreindre_a_des_identifiants_limite_le_balayage(): void
    {
        $vise = Booking::factory()->create(['status' => 'en_attente']);
        Booking::factory()->create(['status' => 'en_attente']);   // TEMOIN : non vise

        $passage = app(RuleRunner::class)->executer(
            $this->regle(AutomationRule::ETAT_ARMEE),
            [$vise->id]
        );

        $this->assertSame(1, $passage->entites_vues);
    }
}

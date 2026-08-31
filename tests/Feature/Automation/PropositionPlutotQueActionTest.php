<?php

namespace Tests\Feature\Automation;

use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\Automation\RegleDeclencheeNotification;
use App\Services\Automation\RuleRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Armee, une regle n'agit que si l'action est autonome ; sinon elle propose sans rien executer. */
class PropositionPlutotQueActionTest extends TestCase
{
    use ArmeSesRegles;
    use RefreshDatabase;
    use RendSesActionsAutonomes;

    /** @param array<string, mixed> $attributs */
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

    private function compter(string $resultat, string $mode = 'armee'): int
    {
        return AutomationAction::query()->where('mode', $mode)->where('resultat', $resultat)->count();
    }

    public function test_une_action_non_autonome_propose_et_n_execute_pas(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);

        $regle = $this->armer($this->regle(AutomationRule::ETAT_ARMEE));
        $passage = app(RuleRunner::class)->executer($regle);

        $this->assertSame(1, $this->compter(AutomationAction::RESULTAT_PROPOSEE));
        $this->assertSame(0, $this->compter(AutomationAction::RESULTAT_EXECUTEE));
        // L'EFFET, pas l'appel : `journaliser` ecrit au journal d'activite, reste vide ici.
        $this->assertDatabaseMissing('activity_logs', ['action' => 'automation.note']);
        $this->assertSame(1, $passage->actions_posees);
        $this->assertSame('ok', $passage->statut);
    }

    /** TEMOIN — la meme action, rendue autonome, execute et laisse sa trace. */
    public function test_temoin_la_meme_action_basculee_autonome_s_execute(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);
        $this->rendreAutonome('journaliser');

        $regle = $this->armer($this->regle(AutomationRule::ETAT_ARMEE));
        app(RuleRunner::class)->executer($regle);

        $this->assertSame(1, $this->compter(AutomationAction::RESULTAT_EXECUTEE));
        $this->assertSame(0, $this->compter(AutomationAction::RESULTAT_PROPOSEE));
        $this->assertDatabaseHas('activity_logs', ['action' => 'automation.note']);
    }

    /** Second effet observable, d'une autre nature : aucune notification ne part. */
    public function test_une_action_non_autonome_n_envoie_aucune_notification(): void
    {
        Notification::fake();
        User::factory()->admin()->create(['is_active' => true]);
        Booking::factory()->create(['status' => 'en_attente']);

        $regle = $this->armer($this->regle(AutomationRule::ETAT_ARMEE, [
            'actions' => [['cle' => 'notifier.admins', 'parametres' => ['message' => 'x']]],
        ]));
        app(RuleRunner::class)->executer($regle);

        Notification::assertNothingSent();
        $this->assertSame(1, $this->compter(AutomationAction::RESULTAT_PROPOSEE));
    }

    /** TEMOIN — rendue autonome, la meme notification part vraiment. */
    public function test_temoin_la_meme_notification_basculee_autonome_part(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create(['is_active' => true]);
        Booking::factory()->create(['status' => 'en_attente']);
        $this->rendreAutonome('notifier.admins');

        $regle = $this->armer($this->regle(AutomationRule::ETAT_ARMEE, [
            'actions' => [['cle' => 'notifier.admins', 'parametres' => ['message' => 'x']]],
        ]));
        app(RuleRunner::class)->executer($regle);

        Notification::assertSentTo($admin, RegleDeclencheeNotification::class);
        $this->assertSame(1, $this->compter(AutomationAction::RESULTAT_EXECUTEE));
    }

    /** L'ORDRE DES DEUX GARDES : l'observation passe AVANT l'autonomie, jamais l'inverse. */
    public function test_en_observation_une_action_non_autonome_reste_simulee(): void
    {
        Booking::factory()->count(2)->create(['status' => 'en_attente']);

        app(RuleRunner::class)->executer($this->regle(AutomationRule::ETAT_OBSERVATION));

        $this->assertSame(2, $this->compter(AutomationAction::RESULTAT_SIMULEE, 'observation'));
        $this->assertSame(0, AutomationAction::where('resultat', AutomationAction::RESULTAT_PROPOSEE)->count());
        $this->assertDatabaseMissing('activity_logs', ['action' => 'automation.note']);
    }

    /** TEMOIN de l'ordre — autonome ou pas, une observation n'appelle jamais l'action. */
    public function test_en_observation_une_action_autonome_reste_simulee(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);
        $this->rendreAutonome('journaliser');

        app(RuleRunner::class)->executer($this->regle(AutomationRule::ETAT_OBSERVATION));

        $this->assertSame(1, $this->compter(AutomationAction::RESULTAT_SIMULEE, 'observation'));
        $this->assertSame(0, AutomationAction::where('resultat', AutomationAction::RESULTAT_EXECUTEE)->count());
        $this->assertDatabaseMissing('activity_logs', ['action' => 'automation.note']);
    }

    /** Le contrepoids : une proposition en attente GELE l'entite, personne ne repasse dessus. */
    public function test_une_entite_avec_une_proposition_en_attente_n_est_pas_reprise(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);

        $regle = $this->armer($this->regle(AutomationRule::ETAT_ARMEE));
        app(RuleRunner::class)->executer($regle);

        $second = app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame(0, $second->entites_vues);
        $this->assertSame(1, $this->compter(AutomationAction::RESULTAT_PROPOSEE));
    }

    /** TEMOIN du gel — une proposition refusee rend l'entite au balayage suivant. */
    public function test_temoin_une_proposition_refusee_libere_l_entite(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);

        $regle = $this->armer($this->regle(AutomationRule::ETAT_ARMEE));
        app(RuleRunner::class)->executer($regle);

        AutomationAction::query()
            ->where('resultat', AutomationAction::RESULTAT_PROPOSEE)
            ->update(['resultat' => AutomationAction::RESULTAT_REFUSEE]);

        $second = app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame(1, $second->entites_vues);
        $this->assertSame(1, $this->compter(AutomationAction::RESULTAT_PROPOSEE));
    }
}

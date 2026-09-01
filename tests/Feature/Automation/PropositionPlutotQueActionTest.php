<?php

namespace Tests\Feature\Automation;

use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\Automation\RegleDeclencheeNotification;
use App\Services\Automation\FileDePropositions;
use App\Services\Automation\RuleRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    /**
     * TEMOIN du gel — une proposition EXPIREE rend l'entite au balayage suivant, sous
     * `une_fois` comme ailleurs : personne n'a decide, il faut redemander.
     */
    public function test_temoin_une_proposition_expiree_libere_l_entite(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);

        $regle = $this->armer($this->regle(AutomationRule::ETAT_ARMEE));
        app(RuleRunner::class)->executer($regle);

        AutomationAction::query()
            ->where('resultat', AutomationAction::RESULTAT_PROPOSEE)
            ->update(['resultat' => AutomationAction::RESULTAT_EXPIREE]);

        $second = app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame(1, $second->entites_vues);
        $this->assertSame(1, $this->compter(AutomationAction::RESULTAT_PROPOSEE));
    }

    /** Refuse par le chemin reel — jamais par un `update()` qui contournerait le verrou. */
    private function refuserLaPropositionEnAttente(): void
    {
        $ligne = AutomationAction::query()->where('resultat', AutomationAction::RESULTAT_PROPOSEE)->firstOrFail();

        app(FileDePropositions::class)->refuser($ligne, User::factory()->create(), 'Non, pas cette entité.');
    }

    /**
     * UN REFUS EST UNE DECISION, PAS UN ECHEC. Sous `une_fois`, l'administrateur a dit non une
     * fois pour toutes : le passage suivant ne doit pas defaire son refus a la minute.
     */
    public function test_une_proposition_refusee_n_est_jamais_reproposee_sous_une_fois(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);

        $regle = $this->armer($this->regle(AutomationRule::ETAT_ARMEE));
        app(RuleRunner::class)->executer($regle);
        $this->refuserLaPropositionEnAttente();

        $second = app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame(0, $second->entites_vues);
        $this->assertSame(0, $this->compter(AutomationAction::RESULTAT_PROPOSEE));
        $this->assertSame(1, $this->compter(AutomationAction::RESULTAT_REFUSEE));
    }

    /** C'est la POLITIQUE qui dit quand redemander : sous `chaque_passage`, au passage suivant. */
    public function test_une_proposition_refusee_est_reproposee_sous_chaque_passage(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);

        $regle = $this->armer($this->regle(AutomationRule::ETAT_ARMEE, [
            'politique_reprise' => 'chaque_passage',
        ]));
        app(RuleRunner::class)->executer($regle);
        $this->refuserLaPropositionEnAttente();

        $second = app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame(1, $second->entites_vues);
        $this->assertSame(1, $this->compter(AutomationAction::RESULTAT_PROPOSEE));
    }

    /** Et sous `une_fois_par_jour` : pas le meme jour, mais le lendemain — avec son temoin. */
    public function test_une_proposition_refusee_n_est_reproposee_que_le_lendemain_sous_une_fois_par_jour(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);

        $regle = $this->armer($this->regle(AutomationRule::ETAT_ARMEE, [
            'politique_reprise' => 'une_fois_par_jour',
        ]));
        app(RuleRunner::class)->executer($regle);
        $this->refuserLaPropositionEnAttente();

        $this->assertSame(0, app(RuleRunner::class)->executer($regle->fresh())->entites_vues);

        $this->travel(25)->hours();

        $this->assertSame(1, app(RuleRunner::class)->executer($regle->fresh())->entites_vues);
        $this->assertSame(1, $this->compter(AutomationAction::RESULTAT_PROPOSEE));
    }

    /** Les reglages se lisent UNE fois par passage : dans la boucle, c'est une requete par ligne. */
    public function test_les_reglages_ne_sont_lus_qu_une_fois_par_passage(): void
    {
        Booking::factory()->count(10)->create(['status' => 'en_attente']);

        $regle = $this->armer($this->regle(AutomationRule::ETAT_ARMEE, [
            'actions' => [
                ['cle' => 'journaliser', 'parametres' => ['message' => 'a']],
                ['cle' => 'notifier.admins', 'parametres' => ['message' => 'b']],
            ],
        ]));

        // Cadre sur la SEULE table des reglages : stable quoi qu'on ajoute ailleurs, la ou un
        // budget de requetes global rougirait au premier ajout legitime.
        $lectures = 0;
        DB::listen(function ($requete) use (&$lectures) {
            if (str_contains($requete->sql, 'automation_action_settings')) {
                $lectures++;
            }
        });

        $passage = app(RuleRunner::class)->executer($regle);

        $this->assertSame(1, $lectures);
        // TEMOIN — le passage a vraiment travaille : sans lui, « une seule lecture » serait
        // tout aussi vrai d'un passage qui ne pose rien du tout.
        $this->assertSame(10, $passage->entites_vues);
        $this->assertSame(20, $passage->actions_posees);
        $this->assertSame(20, $this->compter(AutomationAction::RESULTAT_PROPOSEE));
    }

    /** `chaque_passage` fait re-AGIR, pas re-PROPOSER : sinon la file se remplit de doublons. */
    public function test_chaque_passage_ne_repropose_pas_une_entite_qui_attend_une_decision(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);

        $regle = $this->armer($this->regle(AutomationRule::ETAT_ARMEE, [
            'politique_reprise' => 'chaque_passage',
        ]));

        app(RuleRunner::class)->executer($regle);
        $second = app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame(0, $second->entites_vues);
        $this->assertSame(1, $this->compter(AutomationAction::RESULTAT_PROPOSEE));
    }

    /** TEMOIN — la politique garde tout son sens des qu'il y a vraiment action. */
    public function test_temoin_chaque_passage_agit_bien_aux_deux_passages_si_l_action_est_autonome(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);
        $this->rendreAutonome('journaliser');

        $regle = $this->armer($this->regle(AutomationRule::ETAT_ARMEE, [
            'politique_reprise' => 'chaque_passage',
        ]));

        app(RuleRunner::class)->executer($regle);
        $second = app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame(1, $second->entites_vues);
        $this->assertSame(2, $this->compter(AutomationAction::RESULTAT_EXECUTEE));
    }

    /** Meme invariant sur la fenetre de 24 h : elle oublie les lignes, pas les propositions. */
    public function test_une_fois_par_jour_ne_repropose_pas_le_lendemain(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);

        $regle = $this->armer($this->regle(AutomationRule::ETAT_ARMEE, [
            'politique_reprise' => 'une_fois_par_jour',
        ]));
        app(RuleRunner::class)->executer($regle);

        $this->travel(25)->hours();
        $lendemain = app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame(0, $lendemain->entites_vues);
        $this->assertSame(1, $this->compter(AutomationAction::RESULTAT_PROPOSEE));
    }

    /** TEMOIN — la fenetre de 24 h fonctionne toujours quand l'action agit vraiment. */
    public function test_temoin_une_fois_par_jour_agit_le_lendemain_si_l_action_est_autonome(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);
        $this->rendreAutonome('journaliser');

        $regle = $this->armer($this->regle(AutomationRule::ETAT_ARMEE, [
            'politique_reprise' => 'une_fois_par_jour',
        ]));
        app(RuleRunner::class)->executer($regle);

        $this->travel(25)->hours();

        $this->assertSame(1, app(RuleRunner::class)->executer($regle->fresh())->entites_vues);
        $this->assertSame(2, $this->compter(AutomationAction::RESULTAT_EXECUTEE));
    }
}

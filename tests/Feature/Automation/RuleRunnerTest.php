<?php

namespace Tests\Feature\Automation;

use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\Booking;
use App\Services\Automation\ActionResult;
use App\Services\Automation\Contracts\Action;
use App\Services\Automation\Registre\ActionRegistre;
use App\Services\Automation\RuleRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class RuleRunnerTest extends TestCase
{
    use ArmeSesRegles;
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

        // Arme par le chemin reel : elle observe d'abord (2 lignes `simulee` en plus).
        $regle = $this->armer($this->regle(AutomationRule::ETAT_ARMEE));
        $passage = app(RuleRunner::class)->executer($regle);

        $this->assertSame('armee', $passage->mode);
        $this->assertSame(2, AutomationAction::where('mode', 'armee')->where('resultat', 'executee')->count());
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
        // L'observation simule 'notifier.admins' (l'action existe) : elle n'appelle jamais
        // l'echec reel, donc ne pose aucune ligne `echouee` en plus.
        $regle = $this->armer($regle);

        $passage = app(RuleRunner::class)->executer($regle);

        $this->assertSame(3, $passage->entites_vues);
        $this->assertSame(3, AutomationAction::where('mode', 'armee')->where('resultat', 'echouee')->count());
    }

    public function test_une_action_inconnue_est_journalisee_en_echec(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);

        // Une action inconnue echoue MEME en observation : le journal resterait vide et
        // l'armement serait refuse. On observe avec une action valide, puis on la remplace.
        $regle = $this->armer($this->regle(AutomationRule::ETAT_ARMEE));
        $regle->forceFill([
            'actions' => [['cle' => 'action.qui.n.existe.pas', 'parametres' => []]],
        ])->save();

        app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame(1, AutomationAction::where('mode', 'armee')->where('resultat', 'echouee')->count());
    }

    /** DEFAUT A1 — l'observation obligatoire ne doit pas empoisonner le registre de la regle armee. */
    public function test_une_regle_armee_apres_observation_agit_sur_les_entites_observees(): void
    {
        Booking::factory()->count(3)->create(['status' => 'en_attente']);

        $regle = $this->regle(AutomationRule::ETAT_OBSERVATION);
        app(RuleRunner::class)->executer($regle);

        $regle->forceFill(['etat' => AutomationRule::ETAT_ARMEE])->save();
        $passage = app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame(3, $passage->entites_vues);
        $this->assertSame(3, AutomationAction::where('mode', 'armee')->where('resultat', 'executee')->count());
    }

    /** DEFAUT A2 — un echec transitoire ne condamne pas l'entite a jamais. */
    public function test_une_entite_en_echec_est_reprise_au_passage_suivant(): void
    {
        Notification::fake();
        Booking::factory()->create(['status' => 'en_attente']);

        // Aucun administrateur : `notifier.admins` echoue au 1er passage.
        $regle = $this->regle(AutomationRule::ETAT_ARMEE, [
            'actions' => [['cle' => 'notifier.admins', 'parametres' => ['message' => 'x']]],
        ]);
        $regle = $this->armer($regle);

        app(RuleRunner::class)->executer($regle);
        $passage = app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame(1, $passage->entites_vues);
    }

    public function test_restreindre_a_des_identifiants_limite_le_balayage(): void
    {
        $vise = Booking::factory()->create(['status' => 'en_attente']);
        Booking::factory()->create(['status' => 'en_attente']);   // TEMOIN : non vise

        $regle = $this->armer($this->regle(AutomationRule::ETAT_ARMEE));

        $passage = app(RuleRunner::class)->executer($regle, [$vise->id]);

        $this->assertSame(1, $passage->entites_vues);
    }

    /** DEFAUT B9 — un passage d'observation entierement en echec le montre, il ne le maquille pas. */
    public function test_un_passage_d_observation_entierement_en_echec_est_marque_echec(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);

        $regle = $this->regle(AutomationRule::ETAT_OBSERVATION, [
            'actions' => [['cle' => 'action.qui.n.existe.pas', 'parametres' => []]],
        ]);

        $passage = app(RuleRunner::class)->executer($regle);

        $this->assertSame('echec', $passage->statut);
        // La decision de suspendre reste reservee au mode arme : l'observation ne bouge rien.
        $this->assertSame(AutomationRule::ETAT_OBSERVATION, $regle->fresh()->etat);
        $this->assertSame(0, $regle->fresh()->echecs_consecutifs);
    }

    /** TEMOIN — un passage d'observation qui reussit reste 'ok'. */
    public function test_temoin_un_passage_d_observation_reussi_reste_ok(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);

        $passage = app(RuleRunner::class)->executer($this->regle(AutomationRule::ETAT_OBSERVATION));

        $this->assertSame('ok', $passage->statut);
    }

    /** DEFAUT B6 — un message d'exception accentue n'est pas tronque au milieu d'un caractere. */
    public function test_un_message_d_exception_accentue_n_est_pas_tronque_au_milieu_d_un_caractere(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);

        // 249 octets ASCII puis un 'é' (2 octets) : substr(...,0,250) coupe EXACTEMENT
        // apres le 1er octet du 'é', un octet invalide seul. mb_substr coupe le caractere entier.
        $messageLong = str_repeat('a', 249).'é'.str_repeat('b', 50);

        $action = new class($messageLong) implements Action
        {
            public function __construct(private readonly string $messageLong) {}

            public function cle(): string
            {
                return 'action.qui.explose';
            }

            public function libelle(): string
            {
                return 'Explose';
            }

            public function entitesSupportees(): array
            {
                return ['booking'];
            }

            public function champs(): array
            {
                return [];
            }

            public function toucheAuDomaine(): bool
            {
                return false;
            }

            public function executer(Model $entite, array $parametres): ActionResult
            {
                throw new RuntimeException($this->messageLong);
            }
        };
        app(ActionRegistre::class)->enregistrer($action);

        $regle = $this->armer($this->regle(AutomationRule::ETAT_ARMEE, [
            'actions' => [['cle' => 'action.qui.explose', 'parametres' => []]],
        ]));

        app(RuleRunner::class)->executer($regle);

        $ligne = AutomationAction::where('mode', 'armee')->where('resultat', 'echouee')->first();
        $this->assertNotNull($ligne);
        $this->assertTrue(mb_check_encoding($ligne->message, 'UTF-8'));
        $this->assertSame(250, mb_strlen($ligne->message));
    }
}

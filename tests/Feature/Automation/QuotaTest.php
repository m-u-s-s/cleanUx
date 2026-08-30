<?php

namespace Tests\Feature\Automation;

use App\Models\ActivityLog;
use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\Booking;
use App\Services\Automation\EtatDeRegle;
use App\Services\Automation\RuleRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class QuotaTest extends TestCase
{
    use ArmeSesRegles;
    use RefreshDatabase;

    private function regle(int $quota): AutomationRule
    {
        return AutomationRule::create([
            'nom' => 'Les réservations en attente',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'cadence' => 'quart_heure',
            'conditions' => ['field' => 'statut', 'op' => 'eq', 'value' => 'en_attente'],
            'actions' => [['cle' => 'journaliser', 'parametres' => ['message' => 'vue']]],
            'etat' => AutomationRule::ETAT_ARMEE,
            'politique_reprise' => 'chaque_passage',
            'quota_par_passage' => $quota,
        ]);
    }

    public function test_le_quota_bride_le_passage_sans_suspendre_la_regle(): void
    {
        Booking::factory()->count(5)->create(['status' => 'en_attente']);
        $regle = $this->armer($this->regle(2));

        $passage = app(RuleRunner::class)->executer($regle);

        $this->assertSame(2, $passage->entites_vues);
        $this->assertSame('plafond_atteint', $passage->statut);
        $this->assertSame(AutomationRule::ETAT_ARMEE, $regle->fresh()->etat);
    }

    /**
     * L'emballement se lit contre le passage precedent : le 1er passage n'a pas de precedent
     * et ne peut donc jamais compter. Il faut 4 passages pour suspendre, pas 3.
     */
    public function test_trois_plafonds_consecutifs_suspendent_la_regle(): void
    {
        Booking::factory()->count(5)->create(['status' => 'en_attente']);
        $regle = $this->armer($this->regle(2));

        app(RuleRunner::class)->executer($regle);
        app(RuleRunner::class)->executer($regle->fresh());
        app(RuleRunner::class)->executer($regle->fresh());
        app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame(AutomationRule::ETAT_SUSPENDUE, $regle->fresh()->etat);

        // DEFAUT B2 — la suspension automatique se journalise, avec un motif qui nomme l'emballement.
        $log = ActivityLog::where('action', 'automation.regle_suspendue')
            ->where('target_id', $regle->id)
            ->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('plafond', (string) $log->meta['motif']);
    }

    /**
     * TEMOIN — un passage SOUS le plafond remet le compteur a zero. Sans lui, une regle
     * saine finirait suspendue au bout de trois passages charges espaces dans le temps.
     * (3 passages pour atteindre 2 : le 1er ne compte jamais, voir le test precedent.)
     */
    public function test_temoin_un_passage_sous_le_plafond_remet_le_compteur_a_zero(): void
    {
        Booking::factory()->count(5)->create(['status' => 'en_attente']);
        $regle = $this->armer($this->regle(2));

        app(RuleRunner::class)->executer($regle);
        app(RuleRunner::class)->executer($regle->fresh());
        app(RuleRunner::class)->executer($regle->fresh());
        $this->assertSame(2, $regle->fresh()->plafonds_consecutifs);

        Booking::query()->update(['status' => 'confirme']);   // plus rien a traiter

        app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame(0, $regle->fresh()->plafonds_consecutifs);
        $this->assertSame(AutomationRule::ETAT_ARMEE, $regle->fresh()->etat);
    }

    /** TEMOIN — vider un arriere n'est pas un emballement : la population diminue. */
    public function test_une_regle_qui_vide_un_arriere_n_est_pas_suspendue(): void
    {
        Booking::factory()->count(7)->create(['status' => 'en_attente']);
        $regle = $this->regle(2);
        $regle->forceFill(['politique_reprise' => 'une_fois'])->save();
        $regle = $this->armer($regle);

        foreach (range(1, 4) as $ignore) {
            app(RuleRunner::class)->executer($regle->fresh());
        }

        $this->assertSame(AutomationRule::ETAT_ARMEE, $regle->fresh()->etat);
        // Scope au mode arme : l'armement a deja pose des lignes `simulee` en observation.
        $this->assertSame(7, AutomationAction::where('automation_rule_id', $regle->id)->where('mode', 'armee')->count());
    }

    /** DEFAUT A5 — observer une grosse population ne doit jamais suspendre : rien n'a ete fait. */
    public function test_une_regle_en_observation_n_est_jamais_suspendue_par_le_plafond(): void
    {
        Booking::factory()->count(10)->create(['status' => 'en_attente']);
        $regle = $this->regle(2);
        $regle->forceFill(['etat' => AutomationRule::ETAT_OBSERVATION])->save();

        foreach (range(1, 5) as $ignore) {
            app(RuleRunner::class)->executer($regle->fresh());
        }

        $this->assertSame(AutomationRule::ETAT_OBSERVATION, $regle->fresh()->etat);
    }

    /** DEFAUT A6 — trois passages entierement en echec suspendent la regle. */
    public function test_trois_passages_entierement_en_echec_suspendent_la_regle(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);
        // Une action inconnue echoue meme en observation : impossible de s'armer avec elle.
        // On observe avec une action valide, puis on la remplace par l'action inconnue.
        $regle = $this->armer($this->regleAvecActions(
            [['cle' => 'journaliser', 'parametres' => ['message' => 'obs']]],
            50,
            500
        ));
        $regle->forceFill(['actions' => [['cle' => 'action_qui_n_existe_pas', 'parametres' => []]]])->save();

        app(RuleRunner::class)->executer($regle->fresh());
        app(RuleRunner::class)->executer($regle->fresh());
        $passage = app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame('echec', $passage->statut);
        $this->assertSame(AutomationRule::ETAT_SUSPENDUE, $regle->fresh()->etat);

        // DEFAUT B2 — motif distinct de l'emballement : les deux ne se lisent pas pareil au journal.
        $log = ActivityLog::where('action', 'automation.regle_suspendue')
            ->where('target_id', $regle->id)
            ->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('échec', (string) $log->meta['motif']);
    }

    /** TEMOIN — un seul passage qui reussit remet le compteur d'echecs a zero. */
    public function test_temoin_un_passage_reussi_remet_le_compteur_d_echecs_a_zero(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);
        $regle = $this->armer($this->regleAvecActions(
            [['cle' => 'journaliser', 'parametres' => ['message' => 'obs']]],
            50,
            500
        ));
        $regle->forceFill(['actions' => [['cle' => 'action_qui_n_existe_pas', 'parametres' => []]]])->save();

        app(RuleRunner::class)->executer($regle->fresh());
        app(RuleRunner::class)->executer($regle->fresh());
        $this->assertSame(2, $regle->fresh()->echecs_consecutifs);

        $regle->forceFill(['actions' => [['cle' => 'journaliser', 'parametres' => ['message' => 'ok']]]])->save();
        app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame(0, $regle->fresh()->echecs_consecutifs);
        $this->assertSame(AutomationRule::ETAT_ARMEE, $regle->fresh()->etat);
    }

    /** DECISION B2 — la suspension remet `echecs_consecutifs` a zero, sinon un rearmement se re-suspend aussitot. */
    public function test_rearmer_apres_suspension_par_echecs_repart_a_zero(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);
        $regle = $this->armer($this->regleAvecActions(
            [['cle' => 'journaliser', 'parametres' => ['message' => 'obs']]],
            50,
            500
        ));
        $regle->forceFill(['actions' => [['cle' => 'action_qui_n_existe_pas', 'parametres' => []]]])->save();

        app(RuleRunner::class)->executer($regle->fresh());
        app(RuleRunner::class)->executer($regle->fresh());
        app(RuleRunner::class)->executer($regle->fresh());
        $this->assertSame(AutomationRule::ETAT_SUSPENDUE, $regle->fresh()->etat);

        app(EtatDeRegle::class)->armer($regle->fresh());
        $this->assertSame(0, $regle->fresh()->echecs_consecutifs);

        $regle->forceFill(['actions' => [['cle' => 'journaliser', 'parametres' => ['message' => 'ok']]]])->save();
        $passage = app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame('ok', $passage->statut);
        $this->assertSame(AutomationRule::ETAT_ARMEE, $regle->fresh()->etat);
    }

    public function test_le_plafond_journalier_arrete_la_regle(): void
    {
        Booking::factory()->count(4)->create(['status' => 'en_attente']);

        $regle = $this->regle(10);
        $regle->forceFill(['plafond_journalier' => 2])->save();
        $regle = $this->armer($regle);

        $passage = app(RuleRunner::class)->executer($regle);

        // Scope au mode arme : l'observation a son propre budget journalier, deja depense.
        $this->assertSame(2, AutomationAction::where('mode', 'armee')->count());
        $this->assertSame('plafond_atteint', $passage->statut);
    }

    /** @param  list<array<string, mixed>>  $actions */
    private function regleAvecActions(array $actions, int $quota, int $plafond): AutomationRule
    {
        return AutomationRule::create([
            'nom' => 'Les réservations en attente',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'cadence' => 'quart_heure',
            'conditions' => ['field' => 'statut', 'op' => 'eq', 'value' => 'en_attente'],
            'actions' => $actions,
            'etat' => AutomationRule::ETAT_ARMEE,
            'politique_reprise' => 'chaque_passage',
            'quota_par_passage' => $quota,
            'plafond_journalier' => $plafond,
        ]);
    }

    /** DEFAUT A — ce que la regle a SIMULE ne doit pas manger ce qu'elle peut FAIRE. */
    public function test_les_lignes_d_observation_ne_mangent_pas_le_plafond_arme(): void
    {
        Booking::factory()->count(4)->create(['status' => 'en_attente']);
        $regle = $this->regleAvecActions([['cle' => 'journaliser', 'parametres' => ['message' => 'vue']]], 10, 3);

        // Trois lignes d'observation, alors que le plafond journalier vaut 3. `executer()`
        // derive le mode de `etat` : pas de parametre dedie, on bascule l'etat comme `armer()` le fait.
        $regle->forceFill(['etat' => AutomationRule::ETAT_OBSERVATION])->save();
        app(RuleRunner::class)->executer($regle->fresh());
        $this->assertSame(3, AutomationAction::where('mode', 'observation')->count());

        $regle->forceFill(['etat' => AutomationRule::ETAT_ARMEE])->save();
        $passage = app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame(3, $passage->entites_vues, 'La simulation a mange le plafond de la regle armee.');
        $this->assertSame(3, AutomationAction::where('mode', 'armee')->count());
    }

    /** TEMOIN — le plafond arme bride bel et bien quand ce sont de VRAIES lignes qui l'ont rempli. */
    public function test_temoin_des_lignes_armees_mangent_le_plafond_arme(): void
    {
        Booking::factory()->count(4)->create(['status' => 'en_attente']);
        $regle = $this->armer($this->regleAvecActions(
            [['cle' => 'journaliser', 'parametres' => ['message' => 'vue']]],
            10,
            3
        ));

        app(RuleRunner::class)->executer($regle);
        $passage = app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame(0, $passage->entites_vues, 'Le plafond arme ne bride plus rien.');
    }

    /** DEFAUT B — deux actions par entite depensent deux lignes de plafond, pas une. */
    public function test_le_plafond_se_compte_en_lignes_pour_une_regle_multi_actions(): void
    {
        Booking::factory()->count(10)->create(['status' => 'en_attente']);
        $regle = $this->armer($this->regleAvecActions([
            ['cle' => 'journaliser', 'parametres' => ['message' => 'une']],
            ['cle' => 'journaliser', 'parametres' => ['message' => 'deux']],
        ], 50, 5));

        $passage = app(RuleRunner::class)->executer($regle);

        // 5 lignes de plafond pour 2 actions : 2 entites, soit 4 lignes. Jamais 5 entites ni 10 lignes.
        $this->assertSame(2, $passage->entites_vues);
        $this->assertSame(4, $passage->actions_posees);
        // Scope au mode arme : l'observation a son propre budget journalier, deja depense.
        $this->assertLessThanOrEqual(
            5,
            AutomationAction::where('automation_rule_id', $regle->id)->where('mode', 'armee')->count()
        );
    }

    /** TEMOIN — a une seule action, le meme plafond de 5 laisse bien passer 5 entites. */
    public function test_temoin_a_une_action_le_plafond_laisse_passer_autant_d_entites(): void
    {
        Booking::factory()->count(10)->create(['status' => 'en_attente']);
        $regle = $this->armer($this->regleAvecActions([['cle' => 'journaliser', 'parametres' => ['message' => 'une']]], 50, 5));

        $passage = app(RuleRunner::class)->executer($regle);

        $this->assertSame(5, $passage->entites_vues);
        $this->assertSame(5, $passage->actions_posees);
    }

    /** Ce que `actions_posees` compte : des LIGNES ECRITES, echecs compris. */
    public function test_actions_posees_compte_les_lignes_ecrites_meme_quand_l_action_est_inconnue(): void
    {
        Booking::factory()->count(2)->create(['status' => 'en_attente']);
        // L'action valide suffit a observer : l'inconnue echoue dans les deux modes, elle
        // n'empeche pas l'armement tant qu'une AUTRE action du lot a bien simule.
        $regle = $this->armer($this->regleAvecActions([
            ['cle' => 'journaliser', 'parametres' => ['message' => 'une']],
            ['cle' => 'action_qui_n_existe_pas', 'parametres' => []],
        ], 50, 500));

        $passage = app(RuleRunner::class)->executer($regle);

        $this->assertSame(2, $passage->entites_vues);
        $this->assertSame(4, $passage->actions_posees);
        // Scope au mode arme : l'observation a deja pose 2 lignes `echouee` (action inconnue).
        $this->assertSame(
            2,
            AutomationAction::where('mode', 'armee')->where('resultat', AutomationAction::RESULTAT_ECHOUEE)->count()
        );
    }

    /** Une regle sans action ne divise pas par zero : le plafond reste calculable. */
    public function test_une_regle_sans_action_ne_fait_pas_tomber_le_passage(): void
    {
        Booking::factory()->count(3)->create(['status' => 'en_attente']);
        // Une regle sans action ne simule jamais rien : impossible de l'armer telle quelle.
        // On observe avec une action valide, puis on la retire pour le passage arme teste.
        $regle = $this->armer($this->regleAvecActions(
            [['cle' => 'journaliser', 'parametres' => ['message' => 'obs']]],
            50,
            500
        ));
        $regle->forceFill(['actions' => []])->save();

        $passage = app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame(3, $passage->entites_vues);
        $this->assertSame(0, $passage->actions_posees);
        $this->assertSame('ok', $passage->statut);
    }

    /** DEFAUT B8 — le plafond journalier ne regarde qu'AUJOURD'HUI, jamais la veille. */
    public function test_une_ligne_d_hier_ne_consomme_pas_le_plafond_du_jour(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-30 00:20:00'));

        Booking::factory()->count(2)->create(['status' => 'en_attente']);
        $regle = $this->armer($this->regleAvecActions(
            [['cle' => 'journaliser', 'parametres' => ['message' => 'jour']]],
            10,
            2
        ));

        // Posee hier soir, juste avant minuit : elle ne doit rien manger du budget d'aujourd'hui.
        AutomationAction::create([
            'automation_rule_id' => $regle->id,
            'entite_type' => 'booking',
            'entite_id' => 999999,
            'action_cle' => 'journaliser',
            'mode' => 'armee',
            'resultat' => 'executee',
            'pose_le' => Carbon::yesterday()->setTime(23, 55),
        ]);

        $passage = app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame(2, $passage->entites_vues);

        Carbon::setTestNow();
    }

    /** DEFAUT B10 — un passage a la fois bride et entierement en echec affiche 'echec'. */
    public function test_echec_l_emporte_sur_plafond_atteint(): void
    {
        Notification::fake();
        Booking::factory()->count(5)->create(['status' => 'en_attente']);

        // `notifier.admins` echoue sans administrateur actif : chaque ligne posee echoue.
        $regle = $this->armer($this->regleAvecActions(
            [['cle' => 'notifier.admins', 'parametres' => ['message' => 'x']]],
            2,
            500
        ));

        $passage = app(RuleRunner::class)->executer($regle);

        $this->assertSame(2, $passage->entites_vues, 'Bride par le quota : 5 eligibles pour 2.');
        $this->assertSame('echec', $passage->statut);
    }
}

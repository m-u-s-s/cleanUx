<?php

namespace Tests\Feature\Automation;

use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\Booking;
use App\Services\Automation\RuleRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotaTest extends TestCase
{
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
        $regle = $this->regle(2);

        $passage = app(RuleRunner::class)->executer($regle);

        $this->assertSame(2, $passage->entites_vues);
        $this->assertSame('plafond_atteint', $passage->statut);
        $this->assertSame(AutomationRule::ETAT_ARMEE, $regle->fresh()->etat);
    }

    public function test_trois_plafonds_consecutifs_suspendent_la_regle(): void
    {
        Booking::factory()->count(5)->create(['status' => 'en_attente']);
        $regle = $this->regle(2);

        app(RuleRunner::class)->executer($regle);
        app(RuleRunner::class)->executer($regle->fresh());
        app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame(AutomationRule::ETAT_SUSPENDUE, $regle->fresh()->etat);
    }

    /**
     * TEMOIN — un passage SOUS le plafond remet le compteur a zero. Sans lui, une regle
     * saine finirait suspendue au bout de trois passages charges espaces dans le temps.
     */
    public function test_temoin_un_passage_sous_le_plafond_remet_le_compteur_a_zero(): void
    {
        Booking::factory()->count(5)->create(['status' => 'en_attente']);
        $regle = $this->regle(2);

        app(RuleRunner::class)->executer($regle);
        app(RuleRunner::class)->executer($regle->fresh());
        $this->assertSame(2, $regle->fresh()->plafonds_consecutifs);

        Booking::query()->update(['status' => 'confirme']);   // plus rien a traiter

        app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame(0, $regle->fresh()->plafonds_consecutifs);
        $this->assertSame(AutomationRule::ETAT_ARMEE, $regle->fresh()->etat);
    }

    public function test_le_plafond_journalier_arrete_la_regle(): void
    {
        Booking::factory()->count(4)->create(['status' => 'en_attente']);

        $regle = $this->regle(10);
        $regle->forceFill(['plafond_journalier' => 2])->save();

        $passage = app(RuleRunner::class)->executer($regle);

        $this->assertSame(2, AutomationAction::count());
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
        $regle = $this->regleAvecActions([['cle' => 'journaliser', 'parametres' => ['message' => 'vue']]], 10, 3);

        app(RuleRunner::class)->executer($regle);
        $passage = app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame(0, $passage->entites_vues, 'Le plafond arme ne bride plus rien.');
    }

    /** DEFAUT B — deux actions par entite depensent deux lignes de plafond, pas une. */
    public function test_le_plafond_se_compte_en_lignes_pour_une_regle_multi_actions(): void
    {
        Booking::factory()->count(10)->create(['status' => 'en_attente']);
        $regle = $this->regleAvecActions([
            ['cle' => 'journaliser', 'parametres' => ['message' => 'une']],
            ['cle' => 'journaliser', 'parametres' => ['message' => 'deux']],
        ], 50, 5);

        $passage = app(RuleRunner::class)->executer($regle);

        // 5 lignes de plafond pour 2 actions : 2 entites, soit 4 lignes. Jamais 5 entites ni 10 lignes.
        $this->assertSame(2, $passage->entites_vues);
        $this->assertSame(4, $passage->actions_posees);
        $this->assertLessThanOrEqual(5, AutomationAction::where('automation_rule_id', $regle->id)->count());
    }

    /** TEMOIN — a une seule action, le meme plafond de 5 laisse bien passer 5 entites. */
    public function test_temoin_a_une_action_le_plafond_laisse_passer_autant_d_entites(): void
    {
        Booking::factory()->count(10)->create(['status' => 'en_attente']);
        $regle = $this->regleAvecActions([['cle' => 'journaliser', 'parametres' => ['message' => 'une']]], 50, 5);

        $passage = app(RuleRunner::class)->executer($regle);

        $this->assertSame(5, $passage->entites_vues);
        $this->assertSame(5, $passage->actions_posees);
    }

    /** Ce que `actions_posees` compte : des LIGNES ECRITES, echecs compris. */
    public function test_actions_posees_compte_les_lignes_ecrites_meme_quand_l_action_est_inconnue(): void
    {
        Booking::factory()->count(2)->create(['status' => 'en_attente']);
        $regle = $this->regleAvecActions([
            ['cle' => 'journaliser', 'parametres' => ['message' => 'une']],
            ['cle' => 'action_qui_n_existe_pas', 'parametres' => []],
        ], 50, 500);

        $passage = app(RuleRunner::class)->executer($regle);

        $this->assertSame(2, $passage->entites_vues);
        $this->assertSame(4, $passage->actions_posees);
        $this->assertSame(2, AutomationAction::where('resultat', AutomationAction::RESULTAT_ECHOUEE)->count());
    }
}

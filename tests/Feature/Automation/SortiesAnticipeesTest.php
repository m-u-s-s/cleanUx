<?php

namespace Tests\Feature\Automation;

use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\Booking;
use App\Services\Automation\RuleRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Les sorties anticipees de RuleRunner::executer() — garde d'armement (B1), entite
 * inconnue et conditions vides (B3/B4). Aucune ne doit sauter la cadence ni les echecs.
 */
class SortiesAnticipeesTest extends TestCase
{
    use ArmeSesRegles;
    use RefreshDatabase;

    /** @param  array<string, mixed>  $attributs */
    private function regle(array $attributs = []): AutomationRule
    {
        return AutomationRule::create(array_merge([
            'nom' => 'Les réservations en attente',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'cadence' => 'quart_heure',
            'conditions' => ['field' => 'statut', 'op' => 'eq', 'value' => 'en_attente'],
            'actions' => [['cle' => 'journaliser', 'parametres' => ['message' => 'vue']]],
            'etat' => AutomationRule::ETAT_ARMEE,
        ], $attributs));
    }

    /** DEFAUT B1 — une regle armee jamais observee est refusee, et ecrit quand meme la cadence. */
    public function test_une_regle_armee_sans_observation_est_refusee(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);
        $regle = $this->regle();

        $passage = app(RuleRunner::class)->executer($regle);

        $this->assertSame('echec', $passage->statut);
        $this->assertSame("Règle armée sans journal d'observation.", $passage->message);
        $this->assertNull($passage->entites_eligibles);
        $this->assertNotNull($regle->fresh()->dernier_passage_le);
        $this->assertSame(0, AutomationAction::count());
    }

    /** DEFAUT B3 — une entite inconnue ecrit la cadence : sinon la regle boucle a jamais. */
    public function test_une_entite_inconnue_ecrit_la_cadence(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);
        // Observee et armee avec une entite valide, puis cassee : isole l'entite inconnue
        // de la garde B1, qui a deja ete franchie legitimement.
        $regle = $this->armer($this->regle());
        // Remis a null : l'armement vient deja d'ecrire ce champ, on isole CE passage.
        $regle->forceFill(['entite' => 'entite_qui_n_existe_pas', 'dernier_passage_le' => null])->save();

        $passage = app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame('echec', $passage->statut);
        $this->assertSame('Entité inconnue : entite_qui_n_existe_pas', $passage->message);
        $this->assertNull($passage->entites_eligibles);
        $this->assertNotNull($regle->fresh()->dernier_passage_le);
    }

    /** DECISION B3 — un echec reste un echec : 3 passages a entite inconnue suspendent aussi. */
    public function test_trois_passages_a_entite_inconnue_suspendent_la_regle(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);
        $regle = $this->armer($this->regle());
        $regle->forceFill(['entite' => 'entite_qui_n_existe_pas'])->save();

        app(RuleRunner::class)->executer($regle->fresh());
        app(RuleRunner::class)->executer($regle->fresh());
        app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame(AutomationRule::ETAT_SUSPENDUE, $regle->fresh()->etat);
    }

    /** DEFAUT B4 — conditions vides refuse le passage plutot que de balayer toute la table. */
    public function test_conditions_vides_refuse_le_passage_et_ecrit_la_cadence(): void
    {
        Booking::factory()->count(4)->create(['status' => 'en_attente']);
        $regle = $this->armer($this->regle());
        // Remis a null : l'armement vient deja d'ecrire ce champ, on isole CE passage.
        $regle->forceFill(['conditions' => [], 'dernier_passage_le' => null])->save();

        $passage = app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame('echec', $passage->statut);
        $this->assertSame('Aucune condition : la règle balaierait toute la table.', $passage->message);
        $this->assertNull($passage->entites_eligibles);
        $this->assertNotNull($regle->fresh()->dernier_passage_le);
        $this->assertSame(0, AutomationAction::where('mode', 'armee')->count());
    }

    /** TEMOIN — la meme regle, conditions posees, selectionne bien : sans lui B4 pourrait tout bloquer. */
    public function test_temoin_avec_conditions_la_regle_selectionne(): void
    {
        Booking::factory()->count(3)->create(['status' => 'en_attente']);
        Booking::factory()->create(['status' => 'confirme']);   // hors conditions
        $regle = $this->armer($this->regle());

        $passage = app(RuleRunner::class)->executer($regle);

        $this->assertSame(3, $passage->entites_vues);
    }
}

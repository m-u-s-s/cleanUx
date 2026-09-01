<?php

namespace Tests\Feature\Automation;

use App\Console\Commands\ExpirerLesPropositions;
use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\Booking;
use App\Services\Automation\RuleRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/** Depuis la tache 2 le gel d'une proposition est TOTAL : cette commande est le seul chemin qui degele une entite. */
class ExpirationDesPropositionsTest extends TestCase
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

    /** Une ligne `proposee`, telle que RuleRunner::poser() l'ecrirait pour une action non autonome. */
    private function proposition(AutomationRule $regle, Booking $booking): AutomationAction
    {
        return AutomationAction::create([
            'automation_rule_id' => $regle->id,
            'entite_type' => 'booking',
            'entite_id' => $booking->id,
            'action_cle' => 'journaliser',
            'parametres' => ['message' => 'vue'],
            'mode' => 'armee',
            'resultat' => AutomationAction::RESULTAT_PROPOSEE,
            'pose_le' => now(),
        ]);
    }

    private function compter(string $resultat): int
    {
        return AutomationAction::query()->where('resultat', $resultat)->count();
    }

    public function test_une_proposition_plus_vieille_que_le_delai_expire(): void
    {
        $booking = Booking::factory()->create(['status' => 'en_attente']);
        $ligne = $this->proposition($this->regle(), $booking);

        $this->travel(ExpirerLesPropositions::DELAI_HEURES + 1)->hours();

        $this->artisan('automation:expirer-les-propositions')->assertExitCode(0);

        $this->assertSame(AutomationAction::RESULTAT_EXPIREE, $ligne->fresh()->resultat);
    }

    /** TEMOIN — une proposition plus jeune que le delai ne bouge pas. */
    public function test_temoin_une_proposition_plus_jeune_que_le_delai_ne_bouge_pas(): void
    {
        $booking = Booking::factory()->create(['status' => 'en_attente']);
        $ligne = $this->proposition($this->regle(), $booking);

        $this->travel(ExpirerLesPropositions::DELAI_HEURES - 1)->hours();

        $this->artisan('automation:expirer-les-propositions')->assertExitCode(0);

        $this->assertSame(AutomationAction::RESULTAT_PROPOSEE, $ligne->fresh()->resultat);
    }

    /** Aucun des trois resultats deja decides ne doit bouger : expirer, c'est trancher a la place de personne. */
    public function test_une_ligne_deja_decidee_n_est_jamais_expiree(): void
    {
        $regle = $this->regle();
        $validee = $this->proposition($regle, Booking::factory()->create(['status' => 'en_attente']));
        $validee->forceFill(['resultat' => AutomationAction::RESULTAT_VALIDEE])->save();
        $refusee = $this->proposition($regle, Booking::factory()->create(['status' => 'en_attente']));
        $refusee->forceFill(['resultat' => AutomationAction::RESULTAT_REFUSEE])->save();
        $echouee = $this->proposition($regle, Booking::factory()->create(['status' => 'en_attente']));
        $echouee->forceFill(['resultat' => AutomationAction::RESULTAT_ECHOUEE])->save();

        $this->travel(ExpirerLesPropositions::DELAI_HEURES + 1)->hours();

        $this->artisan('automation:expirer-les-propositions')->assertExitCode(0);

        $this->assertSame(AutomationAction::RESULTAT_VALIDEE, $validee->fresh()->resultat);
        $this->assertSame(AutomationAction::RESULTAT_REFUSEE, $refusee->fresh()->resultat);
        $this->assertSame(AutomationAction::RESULTAT_ECHOUEE, $echouee->fresh()->resultat);
    }

    /** TOUS les cas pendants, pas un sous-ensemble : trois entites differentes, un seul passage. */
    public function test_toutes_les_propositions_pendantes_expirent_pas_un_sous_ensemble(): void
    {
        $regle = $this->regle();
        $lignes = collect(range(1, 3))->map(
            fn () => $this->proposition($regle, Booking::factory()->create(['status' => 'en_attente']))
        );

        $this->travel(ExpirerLesPropositions::DELAI_HEURES + 1)->hours();

        $this->artisan('automation:expirer-les-propositions')->assertExitCode(0);

        $this->assertSame(3, $this->compter(AutomationAction::RESULTAT_EXPIREE));
        $lignes->each(fn (AutomationAction $l) => $this->assertSame(AutomationAction::RESULTAT_EXPIREE, $l->fresh()->resultat));
    }

    /** La trace d'une expiration : personne n'a decide, mais motif et decide_le restent lisibles. */
    public function test_une_expiration_laisse_motif_et_decide_le_mais_pas_decide_par(): void
    {
        $booking = Booking::factory()->create(['status' => 'en_attente']);
        $ligne = $this->proposition($this->regle(), $booking);

        $this->travel(ExpirerLesPropositions::DELAI_HEURES + 1)->hours();
        $this->artisan('automation:expirer-les-propositions');

        $fraiche = $ligne->fresh();
        $this->assertNull($fraiche->decide_par);
        $this->assertNotNull($fraiche->decide_le);
        $this->assertNotNull($fraiche->motif);
    }

    /**
     * Simule la course reelle que le verrou protege : une decision humaine arrive ENTRE la
     * selection des candidats et le verrou de CETTE ligne. Sans le recontrole sous verrou,
     * ce test tomberait en ecrivant `expiree` par-dessus une decision deja prise.
     */
    public function test_le_recontrole_sous_verrou_protege_une_decision_survenue_entre_temps(): void
    {
        $booking = Booking::factory()->create(['status' => 'en_attente']);
        $ligne = $this->proposition($this->regle(), $booking);
        $this->travel(ExpirerLesPropositions::DELAI_HEURES + 1)->hours();

        // La ligne etait candidate a l'instant de la selection ; un admin la valide juste apres.
        $ligne->forceFill(['resultat' => AutomationAction::RESULTAT_VALIDEE, 'decide_le' => now()])->save();

        $commande = app(ExpirerLesPropositions::class);
        $methode = new ReflectionMethod($commande, 'expirer');
        $methode->setAccessible(true);
        $resultat = $methode->invoke($commande, $ligne->id);

        $this->assertFalse($resultat, 'Le recontrole doit refuser une ligne qui a change de resultat entre-temps.');
        $this->assertSame(AutomationAction::RESULTAT_VALIDEE, $ligne->fresh()->resultat);
    }

    /** La ligne peut disparaitre entre la selection et le verrou (suppression en cascade) : ignoree, pas en erreur. */
    public function test_une_ligne_disparue_entre_la_selection_et_le_verrou_est_ignoree(): void
    {
        $commande = app(ExpirerLesPropositions::class);
        $methode = new ReflectionMethod($commande, 'expirer');
        $methode->setAccessible(true);

        $resultat = $methode->invoke($commande, 999999);

        $this->assertFalse($resultat);
    }

    /**
     * Le filtre SQL de selection n'est pas qu'une optimisation cosmetique : sans lui, chaque
     * ligne deja decidee ouvrirait quand meme sa propre transaction avant d'etre rejetee par
     * le recontrole sous verrou — couteux sur un journal qui ne fait que grossir.
     */
    public function test_le_filtre_de_selection_evite_d_ouvrir_une_transaction_pour_les_lignes_deja_decidees(): void
    {
        $regle = $this->regle();

        collect(range(1, 5))->each(function () use ($regle) {
            $ligne = $this->proposition($regle, Booking::factory()->create(['status' => 'en_attente']));
            $ligne->forceFill(['resultat' => AutomationAction::RESULTAT_VALIDEE])->save();
        });
        $this->proposition($regle, Booking::factory()->create(['status' => 'en_attente']));

        $this->travel(ExpirerLesPropositions::DELAI_HEURES + 1)->hours();

        $verrousPoses = 0;
        DB::listen(function ($requete) use (&$verrousPoses) {
            // Le SELECT verrouille (lockForUpdate) de expirer() — pas la selection des
            // candidats (colonne "id" seule), ni l'UPDATE final qui suit une expiration reelle.
            if (str_starts_with($requete->sql, 'select *') && str_contains($requete->sql, 'automation_actions')) {
                $verrousPoses++;
            }
        });

        $this->artisan('automation:expirer-les-propositions')->assertExitCode(0);

        $this->assertSame(1, $verrousPoses, 'Seule la ligne encore proposee doit ouvrir une transaction de verrou.');
    }

    /** LE TEST QUI FERME LA BOUCLE — apres expiration, la regle REPREND l'entite et la repropose. */
    public function test_apres_expiration_la_regle_reprend_l_entite_et_la_repropose(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);

        $regle = $this->armer($this->regle());
        app(RuleRunner::class)->executer($regle);
        $this->assertSame(1, $this->compter(AutomationAction::RESULTAT_PROPOSEE));

        // Gelee : un second passage immediat ne revoit rien, exactement comme sans expiration.
        $gele = app(RuleRunner::class)->executer($regle->fresh());
        $this->assertSame(0, $gele->entites_vues);

        $this->travel(ExpirerLesPropositions::DELAI_HEURES + 1)->hours();
        $this->artisan('automation:expirer-les-propositions')->assertExitCode(0);

        $this->assertSame(1, $this->compter(AutomationAction::RESULTAT_EXPIREE));

        $repris = app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame(1, $repris->entites_vues, "L'entite doit etre reprise au passage suivant l'expiration.");
        $this->assertSame(1, $this->compter(AutomationAction::RESULTAT_PROPOSEE), 'Une nouvelle proposition doit remplacer celle expiree.');
    }
}

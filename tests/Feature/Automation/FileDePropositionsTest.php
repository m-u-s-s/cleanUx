<?php

namespace Tests\Feature\Automation;

use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\Automation\RegleDeclencheeNotification;
use App\Services\Automation\DecisionDejaPrise;
use App\Services\Automation\FileDePropositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Ce qu'un humain fait d'une proposition en attente : l'executer, ou la refuser. */
class FileDePropositionsTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $attributs */
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
    private function proposition(Booking $booking, string $cle = 'journaliser', array $parametres = ['message' => 'vue']): AutomationAction
    {
        return AutomationAction::create([
            'automation_rule_id' => $this->regle(['actions' => [['cle' => $cle, 'parametres' => $parametres]]])->id,
            'entite_type' => 'booking',
            'entite_id' => $booking->id,
            'action_cle' => $cle,
            'parametres' => $parametres,
            'mode' => 'armee',
            'resultat' => AutomationAction::RESULTAT_PROPOSEE,
            'pose_le' => now(),
        ]);
    }

    public function test_valider_execute_l_action_et_ecrit_validee(): void
    {
        $booking = Booking::factory()->create(['status' => 'en_attente']);
        $ligne = $this->proposition($booking);
        $admin = User::factory()->create();

        $resultat = app(FileDePropositions::class)->valider($ligne, $admin);

        $this->assertTrue($resultat->reussie);
        $this->assertSame(AutomationAction::RESULTAT_VALIDEE, $ligne->fresh()->resultat);
        // L'EFFET, pas l'appel : journaliser ecrit vraiment au journal d'activite.
        $this->assertDatabaseHas('activity_logs', ['action' => 'automation.note']);
    }

    public function test_valider_renseigne_decide_par_et_decide_le(): void
    {
        $booking = Booking::factory()->create(['status' => 'en_attente']);
        $ligne = $this->proposition($booking);
        $admin = User::factory()->create();

        app(FileDePropositions::class)->valider($ligne, $admin);

        $fraiche = $ligne->fresh();
        $this->assertSame($admin->id, $fraiche->decide_par);
        $this->assertNotNull($fraiche->decide_le);
    }

    public function test_refuser_n_execute_pas_et_ecrit_refusee_avec_son_motif(): void
    {
        $booking = Booking::factory()->create(['status' => 'en_attente']);
        $ligne = $this->proposition($booking);
        $admin = User::factory()->create();

        app(FileDePropositions::class)->refuser($ligne, $admin, 'Faux positif, la réservation est déjà traitée.');

        $fraiche = $ligne->fresh();
        $this->assertSame(AutomationAction::RESULTAT_REFUSEE, $fraiche->resultat);
        $this->assertSame('Faux positif, la réservation est déjà traitée.', $fraiche->motif);
        $this->assertSame($admin->id, $fraiche->decide_par);
        $this->assertNotNull($fraiche->decide_le);
        // L'ABSENCE d'effet, pas seulement l'etat de la ligne : journaliser n'a pas tourne.
        $this->assertDatabaseMissing('activity_logs', ['action' => 'automation.note']);
    }

    /** TEMOIN — une ligne proposee est bien decidable : rien ne l'empeche avant toute decision. */
    public function test_temoin_une_ligne_proposee_est_decidable(): void
    {
        $booking = Booking::factory()->create(['status' => 'en_attente']);
        $ligne = $this->proposition($booking);
        $this->assertSame(AutomationAction::RESULTAT_PROPOSEE, $ligne->resultat);

        app(FileDePropositions::class)->refuser($ligne, User::factory()->create(), 'motif');

        $this->assertSame(AutomationAction::RESULTAT_REFUSEE, $ligne->fresh()->resultat);
    }

    public function test_une_ligne_deja_validee_ne_se_redecide_pas(): void
    {
        $booking = Booking::factory()->create(['status' => 'en_attente']);
        $ligne = $this->proposition($booking);
        app(FileDePropositions::class)->valider($ligne, User::factory()->create());

        $this->expectException(DecisionDejaPrise::class);

        app(FileDePropositions::class)->valider($ligne->fresh(), User::factory()->create());
    }

    public function test_une_ligne_deja_refusee_ne_se_redecide_pas(): void
    {
        $booking = Booking::factory()->create(['status' => 'en_attente']);
        $ligne = $this->proposition($booking);
        app(FileDePropositions::class)->refuser($ligne, User::factory()->create(), 'un premier motif');

        $this->expectException(DecisionDejaPrise::class);

        app(FileDePropositions::class)->refuser($ligne->fresh(), User::factory()->create(), 'un second motif');
    }

    /** L'ARBITRAGE : un echec au moment de valider n'ecrit jamais `validee` — voir le service. */
    public function test_un_echec_a_la_validation_ecrit_echouee_et_non_validee(): void
    {
        $booking = Booking::factory()->create(['status' => 'en_attente']);
        $ligne = $this->proposition($booking, 'notifier.admins', ['message' => 'x']);
        // Aucun administrateur actif en base : notifier.admins echoue reellement, ce n'est pas simule.
        $par = User::factory()->create();

        $resultat = app(FileDePropositions::class)->valider($ligne, $par);

        $this->assertFalse($resultat->reussie);
        $fraiche = $ligne->fresh();
        $this->assertSame(AutomationAction::RESULTAT_ECHOUEE, $fraiche->resultat);
        $this->assertNotSame(AutomationAction::RESULTAT_VALIDEE, $fraiche->resultat);
        $this->assertNotNull($fraiche->message);
        // Un humain a quand meme tranche : decide_par/decide_le survivent a l'echec de l'action.
        $this->assertSame($par->id, $fraiche->decide_par);
        $this->assertNotNull($fraiche->decide_le);
    }

    /** TEMOIN de l'arbitrage — la meme action, avec un destinataire, reussit et ecrit validee. */
    public function test_temoin_la_meme_validation_reussit_avec_un_administrateur_actif(): void
    {
        Notification::fake();
        $destinataire = User::factory()->admin()->create(['is_active' => true]);
        $booking = Booking::factory()->create(['status' => 'en_attente']);
        $ligne = $this->proposition($booking, 'notifier.admins', ['message' => 'x']);

        $resultat = app(FileDePropositions::class)->valider($ligne, User::factory()->create());

        $this->assertTrue($resultat->reussie);
        $this->assertSame(AutomationAction::RESULTAT_VALIDEE, $ligne->fresh()->resultat);
        Notification::assertSentTo($destinataire, RegleDeclencheeNotification::class);
    }

    public function test_une_action_retiree_du_registre_ecrit_echouee_a_la_validation(): void
    {
        $booking = Booking::factory()->create(['status' => 'en_attente']);
        $ligne = $this->proposition($booking, 'action.retiree.du.code', []);

        $resultat = app(FileDePropositions::class)->valider($ligne, User::factory()->create());

        $this->assertFalse($resultat->reussie);
        $fraiche = $ligne->fresh();
        $this->assertSame(AutomationAction::RESULTAT_ECHOUEE, $fraiche->resultat);
        // Le message precis, pas seulement l'echec : sinon le garde-fou dedie serait
        // indiscernable d'une simple erreur PHP attrapee par le filet du try/catch.
        $this->assertSame('Action inconnue : action.retiree.du.code', $fraiche->message);
    }

    public function test_une_entite_disparue_ecrit_echouee_a_la_validation(): void
    {
        $booking = Booking::factory()->create(['status' => 'en_attente']);
        $ligne = $this->proposition($booking);
        $bookingId = $booking->id;
        $booking->delete();

        $resultat = app(FileDePropositions::class)->valider($ligne, User::factory()->create());

        $this->assertFalse($resultat->reussie);
        $fraiche = $ligne->fresh();
        $this->assertSame(AutomationAction::RESULTAT_ECHOUEE, $fraiche->resultat);
        $this->assertSame("Entité introuvable : booking #{$bookingId}", $fraiche->message);
    }

    public function test_en_attente_ne_rend_que_les_lignes_proposees(): void
    {
        $bookings = Booking::factory()->count(3)->create(['status' => 'en_attente']);

        $proposee = $this->proposition($bookings[0]);
        $this->proposition($bookings[1])->forceFill(['resultat' => AutomationAction::RESULTAT_VALIDEE])->save();
        $this->proposition($bookings[2])->forceFill(['resultat' => AutomationAction::RESULTAT_REFUSEE])->save();

        $enAttente = app(FileDePropositions::class)->enAttente();

        $this->assertCount(1, $enAttente);
        $this->assertTrue($enAttente->contains('id', $proposee->id));
    }
}

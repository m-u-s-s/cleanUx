<?php

namespace Tests\Feature\Automation;

use App\Console\Commands\ExpirerLesPropositions;
use App\Livewire\Admin\Automation\PropositionsEnAttente;
use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\User;
use App\Notifications\MissionCheckInPingNotification;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LA VERIFICATION D'ENSEMBLE DE LA PHASE 4 : le chemin ENTIER, sans raccourci — une regle armee
 * par le chemin reel, la commande reelle, une proposition qui gele son entite, l'ecran qui la
 * montre, un administrateur qui decide. Le domaine ne bouge QU'APRES la decision humaine.
 */
class BoutEnBoutDeuxVitessesTest extends TestCase
{
    use ArmeSesRegles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    // ─── Fabriques — memes patrons qu'ActionsDuDomaineTest : le piege de la mission jumelle ───

    /** Une mission en cours, son client joignable : le terrain du ping. */
    private function missionEnCours(): Mission
    {
        $client = User::factory()->create();
        $prestataire = User::factory()->employe()->create(['is_active' => true, 'status' => 'active']);

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'customer_user_id' => $client->id,
            'employe_id' => $prestataire->id,
            'status' => BookingStatus::SUR_PLACE,
        ]);

        // `sur_place` fait NAITRE la mission (RendezVousObserver) : la retrouver, jamais en creer
        // une seconde — la regle en verrait deux pour la meme reservation.
        $mission = Mission::query()->where('booking_id', $booking->id)->firstOrFail();

        $mission->forceFill([
            'status' => MissionStatus::ARRIVED,
            'lead_employee_id' => $prestataire->id,
            'lead_provider_user_id' => $prestataire->id,
        ])->save();

        // La synchronisation a DEJA pose la ligne du lead : on l'avance, on n'en cree pas une
        // seconde — la table porte un index unique (mission, prestataire).
        MissionAssignment::query()->updateOrCreate(
            ['mission_id' => $mission->id, 'user_id' => $prestataire->id],
            [
                'role_on_mission' => 'lead',
                'assignment_status' => 'arrived',
                'assigned_at' => now()->subHour(),
                'accepted_at' => now()->subHour(),
                'arrived_at' => now()->subMinutes(5),
            ],
        );

        return $mission->fresh();
    }

    private function regleSurLaMission(Mission $mission): AutomationRule
    {
        return AutomationRule::create([
            'nom' => 'Ping deux vitesses',
            'entite' => 'mission',
            'declencheur' => 'cadence',
            'cadence' => 'quart_heure',
            // Sur la reservation, pas sur le statut : une mission etrangere ne doit pas entrer.
            'conditions' => ['field' => 'reservation_id', 'op' => 'eq', 'value' => (int) $mission->booking_id],
            'actions' => [['cle' => 'mission.ping_client', 'parametres' => []]],
            'etat' => AutomationRule::ETAT_ARMEE,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'managed_service_zone_id' => null,
            'is_active' => true,
            'permissions' => ['manage-automation'],
        ]);
    }

    private function proposeeUnique(): AutomationAction
    {
        return AutomationAction::query()
            ->where('mode', 'armee')
            ->where('resultat', AutomationAction::RESULTAT_PROPOSEE)
            ->sole();
    }

    /** Arme par le chemin reel, puis releve `dernier_passage_le` : l'observation l'a deja pose
     *  a maintenant, sinon la cadence retiendrait le passage arme qui suit immediatement. */
    private function armerEtRendreDue(AutomationRule $regle): AutomationRule
    {
        $armee = $this->armer($regle);
        $armee->forceFill(['dernier_passage_le' => null])->save();

        return $armee->fresh();
    }

    // ─── Le chemin complet, sans raccourci ──────────────────────────────────────────────────

    public function test_le_chemin_complet_sans_raccourci_proposition_puis_validation_humaine(): void
    {
        config()->set('features.automation', true);

        $mission = $this->missionEnCours();
        // Armee par le chemin reel : observer -> passage -> armer (trait ArmeSesRegles).
        $this->armerEtRendreDue($this->regleSurLaMission($mission));

        $this->artisan('automation:executer')->assertExitCode(0);

        // LE DOMAINE N'A PAS BOUGE : l'effet en base, pas un espion.
        $this->assertNull($mission->booking->fresh()->checkin_ping_sent_at);
        Notification::assertNothingSent();

        // UNE `proposee` ATTEND, et aucune `executee` n'existe.
        $ligne = $this->proposeeUnique();
        $this->assertSame(0, AutomationAction::query()->where('resultat', AutomationAction::RESULTAT_EXECUTEE)->count());

        // L'ECRAN DE LA FILE LA MONTRE — pas un tableau PHP verifie directement.
        $admin = $this->admin();
        Livewire::actingAs($admin)
            ->test(PropositionsEnAttente::class)
            ->assertSee('Mission #'.$mission->id)
            ->assertSee('Envoyer le « tout va bien ? » au client')
            // UN ADMINISTRATEUR LA VALIDE — par le meme appel que l'ecran reel.
            ->call('valider', $ligne->id);

        // ALORS SEULEMENT LE DOMAINE BOUGE.
        $this->assertNotNull($mission->booking->fresh()->checkin_ping_sent_at);
        Notification::assertSentTo($mission->booking->fresh()->client, MissionCheckInPingNotification::class);

        // `executee` (le moteur) NE SE CONFOND JAMAIS AVEC `validee` (un humain).
        $fraiche = $ligne->fresh();
        $this->assertSame(AutomationAction::RESULTAT_VALIDEE, $fraiche->resultat);
        $this->assertSame($admin->id, $fraiche->decide_par);
        $this->assertNotNull($fraiche->decide_le);
        $this->assertSame(0, AutomationAction::query()->where('resultat', AutomationAction::RESULTAT_EXECUTEE)->count());
    }

    // ─── Le chemin du refus ──────────────────────────────────────────────────────────────────

    public function test_le_chemin_du_refus_decide_sans_toucher_au_domaine(): void
    {
        config()->set('features.automation', true);

        $mission = $this->missionEnCours();
        $this->armerEtRendreDue($this->regleSurLaMission($mission));

        $this->artisan('automation:executer')->assertExitCode(0);
        $ligne = $this->proposeeUnique();

        $admin = $this->admin();
        Livewire::actingAs($admin)
            ->test(PropositionsEnAttente::class)
            ->call('ouvrirRefus', $ligne->id)
            ->set('motifRefus', 'Client injoignable, déjà prévenu par téléphone.')
            ->call('confirmerRefus')
            ->assertHasNoErrors();

        $fraiche = $ligne->fresh();
        $this->assertSame(AutomationAction::RESULTAT_REFUSEE, $fraiche->resultat);
        $this->assertSame($admin->id, $fraiche->decide_par);
        $this->assertSame('Client injoignable, déjà prévenu par téléphone.', $fraiche->motif);

        // REFUSER NE TOUCHE PAS AU DOMAINE : ni la colonne, ni la notification.
        $this->assertNull($mission->booking->fresh()->checkin_ping_sent_at);
        Notification::assertNothingSent();
    }

    // ─── Le chemin de l'expiration ───────────────────────────────────────────────────────────

    public function test_le_chemin_de_l_expiration_ne_touche_pas_au_domaine(): void
    {
        config()->set('features.automation', true);

        $mission = $this->missionEnCours();
        $this->armerEtRendreDue($this->regleSurLaMission($mission));

        $this->artisan('automation:executer')->assertExitCode(0);
        $ligne = $this->proposeeUnique();

        $this->travel(ExpirerLesPropositions::DELAI_HEURES + 1)->hours();
        $this->artisan('automation:expirer-les-propositions')->assertExitCode(0);

        $fraiche = $ligne->fresh();
        $this->assertSame(AutomationAction::RESULTAT_EXPIREE, $fraiche->resultat);
        // PERSONNE N'A DECIDE : decide_par reste NUL, motif et decide_le tracent quand meme.
        $this->assertNull($fraiche->decide_par);
        $this->assertNotNull($fraiche->decide_le);
        $this->assertNotNull($fraiche->motif);

        // L'EXPIRATION NE TOUCHE PAS AU DOMAINE NON PLUS.
        $this->assertNull($mission->booking->fresh()->checkin_ping_sent_at);
        Notification::assertNothingSent();
    }
}

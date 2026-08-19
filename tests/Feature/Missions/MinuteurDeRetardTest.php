<?php

namespace Tests\Feature\Missions;

use App\Models\Booking;
use App\Models\CancellationQuestionOption;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Notifications\MissionEnRetardNotification;
use App\Livewire\Client\GererMaMission;
use App\Livewire\Employe\MissionActions;
use App\Services\Missions\MissionDelayService;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;
use Database\Seeders\CancellationPoliciesSeeder;
use Database\Seeders\CancellationQuestionnaireSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LE MINUTEUR DE RETARD — la plateforme parle avant que le client ne constate.
 *
 * Le retard était mesuré depuis toujours, pour un seul usage : décider si l'option « il est en
 * retard » pouvait s'afficher dans le formulaire d'annulation. Autrement dit, on n'en parlait
 * qu'à la personne ayant déjà renoncé. Le fait servait à constater l'échec, jamais à l'éviter.
 *
 * ── UNE SEULE MESURE ─────────────────────────────────────────────────────────────────────────
 *
 * L'annonce et l'option d'annulation gratuite basculent à la même minute, parce qu'elles lisent la
 * même méthode. Un client averti « votre prestataire a vingt-deux minutes de retard » à qui l'on
 * refuserait ensuite le motif « il est en retard » ne lirait pas deux règles : il lirait une panne.
 */
class MinuteurDeRetardTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $prestataire;

    private Mission $mission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CancellationPoliciesSeeder::class);
        $this->seed(CancellationQuestionnaireSeeder::class);
    }

    private function reservation(?Carbon $prevu = null): Booking
    {
        $this->client = User::factory()->client()->create();
        $this->prestataire = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $this->prestataire->id, 'status' => 'active']);

        $prevu ??= Carbon::now()->addHours(6);

        $booking = Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $this->client->id,
            'client_id' => $this->client->id,
            'employe_id' => $this->prestataire->id,
            'status' => BookingStatus::CONFIRME,
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
            'address' => 'Rue de la Loi 1, 1000 Bruxelles',
            'devis_estime' => 120.00,
            'estimated_price' => 120.00,
            'scheduled_at' => $prevu,
            'date' => $prevu->toDateString(),
            'heure' => $prevu->format('H:i:s'),
        ]);

        $this->mission = Mission::create([
            'booking_id' => $booking->id,
            'lead_provider_user_id' => $this->prestataire->id,
            'status' => MissionStatus::ASSIGNED,
        ]);

        MissionAssignment::factory()->accepted()->create([
            'mission_id' => $this->mission->id,
            'user_id' => $this->prestataire->id,
        ]);

        return $booking->fresh();
    }

    private function retards(): MissionDelayService
    {
        return app(MissionDelayService::class);
    }

    // ── LA MESURE ─────────────────────────────────────────────────────────────

    /**
     * LE TÉMOIN POSITIF DE TOUT CE FICHIER.
     *
     * Sans lui, chaque assertion « il n'est pas en retard » passerait au vert sur une mesure en
     * panne : un `minutesDeRetard()` qui rendrait toujours `null` ferait exactement le même effet.
     */
    #[Test]
    public function le_retard_se_mesure_en_minutes(): void
    {
        $enRetard = $this->reservation(Carbon::now()->subMinutes(40));
        $this->assertSame(40, $this->retards()->etat($enRetard)['minutes']);

        $aLHeure = $this->reservation(Carbon::now()->addHours(2));
        $this->assertNull($this->retards()->etat($aLHeure)['minutes']);
        $this->assertFalse($this->retards()->etat($aLHeure)['en_retard']);
    }

    /**
     * LA TOLÉRANCE EXISTE, et elle se lit en configuration.
     *
     * Cinq minutes de décalage ne sont pas un retard : les annoncer transformerait le minuteur en
     * générateur d'inquiétude, et le client couperait ses notifications avant le jour où elles
     * comptent.
     */
    #[Test]
    public function la_tolerance_retient_l_annonce(): void
    {
        config()->set('missions.late_tolerance_minutes', 15);

        $booking = $this->reservation(Carbon::now()->subMinutes(5));

        $this->assertFalse($this->retards()->etat($booking)['en_retard']);
    }

    /** Une intervention COMMENCÉE avec du décalage n'est plus un retard : elle a lieu. */
    #[Test]
    public function une_mission_demarree_n_est_plus_en_retard(): void
    {
        $booking = $this->reservation(Carbon::now()->subMinutes(40));

        $this->assertTrue($this->retards()->etat($booking)['en_retard'], 'témoin : elle l’était');

        $this->mission->forceFill(['actual_start_at' => Carbon::now()->subMinutes(10)])->save();

        $this->assertFalse($this->retards()->etat($booking->fresh())['en_retard']);
    }

    // ── L'ANNONCE ─────────────────────────────────────────────────────────────

    #[Test]
    public function la_commande_previent_le_client_une_seule_fois(): void
    {
        Notification::fake();

        $booking = $this->reservation(Carbon::now()->subMinutes(40));

        $this->artisan('missions:signaler-les-retards')->assertSuccessful();
        Notification::assertSentToTimes($this->client, MissionEnRetardNotification::class, 1);

        $this->artisan('missions:signaler-les-retards')->assertSuccessful();
        Notification::assertSentToTimes($this->client, MissionEnRetardNotification::class, 1);

        $this->assertNotNull($booking->fresh()->late_notified_at);
    }

    /** Le témoin : à l'heure, la commande ne réveille personne. */
    #[Test]
    public function la_commande_ne_previent_pas_quand_tout_va_bien(): void
    {
        Notification::fake();

        $this->reservation(Carbon::now()->addHours(3));

        $this->artisan('missions:signaler-les-retards')->assertSuccessful();

        Notification::assertNothingSent();
    }

    // ── LA RÉPONSE DU PRESTATAIRE ─────────────────────────────────────────────

    #[Test]
    public function le_prestataire_annonce_une_heure_d_arrivee(): void
    {
        $booking = $this->reservation(Carbon::now()->subMinutes(40));

        Sanctum::actingAs($this->prestataire);

        $reponse = $this->postJson("/api/provider/missions/{$this->mission->id}/delay", [
            'minutes' => 20,
            'reason' => 'Embouteillage sur le ring',
        ]);

        $reponse->assertOk();
        $this->assertSame('Embouteillage sur le ring', $reponse->json('data.annonce.motif'));
        $this->assertNotNull($reponse->json('data.annonce.arrivee_at'));

        // Le client la voit, sur son propre chemin.
        Sanctum::actingAs($this->client);
        $vuClient = $this->getJson("/api/client/bookings/{$booking->id}/delay");

        $vuClient->assertOk();
        $this->assertSame('Embouteillage sur le ring', $vuClient->json('data.annonce.motif'));
        $this->assertTrue($vuClient->json('data.en_retard'));
    }

    /** Un étranger ne lit pas le retard d'autrui : c'est une information sur le domicile de quelqu'un. */
    #[Test]
    public function un_etranger_ne_lit_pas_le_retard(): void
    {
        $booking = $this->reservation(Carbon::now()->subMinutes(40));

        Sanctum::actingAs(User::factory()->client()->create());

        $this->getJson("/api/client/bookings/{$booking->id}/delay")->assertForbidden();
    }

    // ── LES TROIS ISSUES ──────────────────────────────────────────────────────

    /**
     * L'ANNULATION GRATUITE SE LIT DANS LE QUESTIONNAIRE, elle ne se recalcule pas.
     *
     * Désactiver l'option depuis la console doit fermer le bouton. Un second barème codé dans le
     * minuteur aurait continué de promettre la gratuité que le formulaire refuserait ensuite.
     */
    #[Test]
    public function l_annulation_gratuite_suit_le_questionnaire(): void
    {
        $booking = $this->reservation(Carbon::now()->subMinutes(40));

        $this->assertTrue($this->retards()->etat($booking)['annulation_gratuite']);

        CancellationQuestionOption::query()
            ->where('verification', CancellationQuestionOption::VERIF_RETARD)
            ->update(['is_active' => false]);

        $this->assertFalse($this->retards()->etat($booking->fresh())['annulation_gratuite']);
    }

    /**
     * DÉCALER PLUTÔT QU'ANNULER — la deuxième issue, et elle éteint le retard.
     *
     * `BookingRescheduleService` n'était atteignable que par le glisser-déposer du calendrier
     * web : le client mobile n'avait le choix qu'entre attendre et annuler.
     */
    #[Test]
    public function le_client_peut_decaler_l_intervention(): void
    {
        $booking = $this->reservation(Carbon::now()->subMinutes(40));

        Sanctum::actingAs($this->client);

        $this->postJson("/api/client/bookings/{$booking->id}/reschedule", [
            'date' => Carbon::now()->addDays(2)->toDateString(),
            'time' => '09:00',
        ])->assertOk();

        $this->assertFalse($this->retards()->etat($booking->fresh())['en_retard']);
    }

    // ── LES SURFACES WEB ──────────────────────────────────────────────────────

    /**
     * LE WEB CLIENT MONTRE LE RETARD ET SES ISSUES.
     *
     * Un module complet et injoignable est la famille de défaut la plus coûteuse de ce dépôt : on
     * PRESSE le bouton, on ne se contente pas de vérifier qu'il s'affiche.
     */
    #[Test]
    public function la_page_client_montre_le_retard_et_sait_decaler(): void
    {
        $booking = $this->reservation(Carbon::now()->subMinutes(40));

        Livewire::actingAs($this->client)
            ->test(GererMaMission::class, ['booking' => $booking])
            ->assertSee('40 min de retard')
            ->assertSee('Plus tard aujourd’hui')
            ->call('decaler', 'demain')
            ->assertHasNoErrors();

        $this->assertFalse($this->retards()->etat($booking->fresh())['en_retard']);
    }

    /** Le témoin : à l'heure, la page ne parle pas de retard. */
    #[Test]
    public function la_page_client_ne_parle_pas_de_retard_quand_il_n_y_en_a_pas(): void
    {
        $booking = $this->reservation(Carbon::now()->addHours(4));

        Livewire::actingAs($this->client)
            ->test(GererMaMission::class, ['booking' => $booking])
            ->assertDontSee('min de retard');
    }

    #[Test]
    public function la_page_prestataire_permet_d_annoncer_une_arrivee(): void
    {
        $booking = $this->reservation(Carbon::now()->subMinutes(40));

        Livewire::actingAs($this->prestataire)
            ->test(MissionActions::class, ['mission' => $this->mission])
            ->assertSee('40 min de retard')
            ->set('motifDuRetard', 'Embouteillage')
            ->call('annoncerLeRetard', 20)
            ->assertHasNoErrors();

        $etat = $this->retards()->etat($booking->fresh());

        $this->assertSame('Embouteillage', $etat['annonce']['motif']);
        $this->assertNotNull($etat['annonce']['arrivee_at']);
    }
}

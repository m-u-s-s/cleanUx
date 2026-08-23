<?php

namespace Tests\Feature\Missions\OnSite;

use App\Events\Missions\MissionExtraProposed;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionExtra;
use App\Models\User;
use App\Notifications\MissionExtraNotification;
use App\Services\Missions\OnSite\MissionExtraService;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** LES SUPPLÉMENTS PROPOSÉS SUR PLACE (F3) ET LA RÉPONSE EN UN GESTE (F12). */
class SupplementsSurPlaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    /** @return array{0: User, 1: Mission, 2: User, 3: Booking} */
    private function scenario(): array
    {
        $client = User::factory()->create();
        $provider = User::factory()->employe()->create(['is_active' => true, 'status' => 'active']);

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'customer_user_id' => $client->id,
            'devis_estime' => 120.00,
        ]);

        $mission = Mission::query()->where('booking_id', $booking->id)->first()
            ?? Mission::factory()->create(['booking_id' => $booking->id]);

        $mission->forceFill([
            'status' => MissionStatus::STARTED,
            'lead_employee_id' => $provider->id,
            'lead_provider_user_id' => $provider->id,
            'actual_start_at' => now()->subHour(),
        ])->save();

        MissionAssignment::query()->create([
            'mission_id' => $mission->id,
            'user_id' => $provider->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'accepted',
            'assigned_at' => now()->subHours(2),
            'accepted_at' => now()->subHours(2),
        ]);

        return [$provider, $mission->fresh(), $client, $booking->fresh()];
    }

    // ── F3 : proposer ────────────────────────────────────────────────────────

    #[Test]
    public function le_prestataire_propose_un_supplement(): void
    {
        [$provider, $mission] = $this->scenario();

        $this->actingAs($provider)
            ->postJson("/api/provider/missions/{$mission->id}/extras", [
                'label' => 'Nettoyage des vitres',
                'price_cents' => 2500,
                'description' => 'Non prévu au devis initial.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', MissionExtra::STATUS_PROPOSED)
            // `round()` d'un montant entier rend un entier en PHP : le JSON porte 25, pas 25.0.
            ->assertJsonPath('data.price', 25)
            ->assertJsonPath('data.awaiting_client', true);

        $this->assertDatabaseHas('mission_extras', [
            'mission_id' => $mission->id,
            'label' => 'Nettoyage des vitres',
            'price_cents' => 2500,
        ]);
    }

    #[Test]
    public function le_client_est_prevenu_tout_de_suite(): void
    {
        [$provider, $mission, $client] = $this->scenario();

        $this->actingAs($provider)
            ->postJson("/api/provider/missions/{$mission->id}/extras", [
                'label' => 'Vitres',
                'price_cents' => 2500,
            ])
            ->assertCreated();

        // LA FENÊTRE EST DE QUELQUES MINUTES.
        Notification::assertSentTo($client, MissionExtraNotification::class);
    }

    #[Test]
    public function la_proposition_est_diffusee_sur_le_canal_de_la_mission(): void
    {
        Event::fake([MissionExtraProposed::class]);

        [$provider, $mission] = $this->scenario();

        $this->actingAs($provider)
            ->postJson("/api/provider/missions/{$mission->id}/extras", [
                'label' => 'Vitres',
                'price_cents' => 2500,
            ])
            ->assertCreated();

        // L'écran de suivi du client écoute déjà `mission.{id}` : la proposition doit s'y afficher
        // sans qu'il ait à recharger, sinon « répondre en un tap » demande d'abord de deviner
        // qu'il y a quelque chose à lire.
        Event::assertDispatched(MissionExtraProposed::class);
    }

    #[Test]
    public function un_prestataire_non_affecte_ne_propose_rien(): void
    {
        [, $mission] = $this->scenario();
        $intrus = User::factory()->employe()->create(['is_active' => true, 'status' => 'active']);

        // L'identifiant de mission est un entier : sans ce contrôle, il suffit d'en essayer un
        // autre pour facturer un supplément chez un inconnu.
        $this->actingAs($intrus)
            ->postJson("/api/provider/missions/{$mission->id}/extras", [
                'label' => 'Vitres',
                'price_cents' => 2500,
            ])
            ->assertForbidden();

        $this->assertSame(0, MissionExtra::query()->count());
    }

    #[Test]
    public function un_montant_deraisonnable_est_refuse(): void
    {
        [$provider, $mission] = $this->scenario();

        // Au-delà du plafond, ce n'est plus un ajustement mais une seconde intervention : elle doit
        // passer par le parcours normal, avec ses conditions d'annulation et sa protection.
        $this->actingAs($provider)
            ->postJson("/api/provider/missions/{$mission->id}/extras", [
                'label' => 'Rénovation complète',
                'price_cents' => MissionExtraService::MAX_PRICE_CENTS + 1,
            ])
            ->assertStatus(422);
    }

    // ── F12 : répondre ───────────────────────────────────────────────────────

    #[Test]
    public function le_client_accepte_en_un_geste(): void
    {
        [$provider, $mission, $client, $booking] = $this->scenario();

        $extra = app(MissionExtraService::class)->propose($mission, $provider, 'Vitres', 2500);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/onsite/extras/{$extra->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.awaiting_client', false);

        $this->assertTrue($extra->fresh()->estAccepte());
    }

    #[Test]
    public function le_devis_d_origine_ne_bouge_pas(): void
    {
        [$provider, $mission, $client, $booking] = $this->scenario();

        $extra = app(MissionExtraService::class)->propose($mission, $provider, 'Vitres', 2500);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/onsite/extras/{$extra->id}/approve")
            ->assertOk();

        // C'EST LA PROMESSE STRUCTURANTE.
        $this->assertSame(120.00, (float) $booking->fresh()->devis_estime);
    }

    #[Test]
    public function le_refus_est_une_reponse_qui_se_conserve(): void
    {
        [$provider, $mission, $client, $booking] = $this->scenario();

        $extra = app(MissionExtraService::class)->propose($mission, $provider, 'Vitres', 2500);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/onsite/extras/{$extra->id}/decline")
            ->assertOk()
            ->assertJsonPath('data.status', MissionExtra::STATUS_DECLINED);

        $this->assertNotNull($extra->fresh()->declined_at);
    }

    #[Test]
    public function on_ne_repond_pas_deux_fois(): void
    {
        [$provider, $mission, $client, $booking] = $this->scenario();

        $extra = app(MissionExtraService::class)->propose($mission, $provider, 'Vitres', 2500);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/onsite/extras/{$extra->id}/approve")
            ->assertOk();

        // Un double appui, ou deux appareils : sans ce refus, un « non » tardif effacerait un accord
        // déjà encaissé.
        $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/onsite/extras/{$extra->id}/decline")
            ->assertStatus(422);

        $this->assertTrue($extra->fresh()->estAccepte());
    }

    #[Test]
    public function le_supplement_d_autrui_reste_hors_de_portee(): void
    {
        [$provider, $mission] = $this->scenario();
        [, , , $autreBooking] = $this->scenario();

        $extra = app(MissionExtraService::class)->propose($mission, $provider, 'Vitres', 2500);

        // L'identifiant du supplément est un entier lui aussi. Sans la vérification qu'il appartient
        // à la mission de CETTE réservation, on ferait payer le supplément de quelqu'un d'autre.
        $this->actingAs($autreBooking->client, 'sanctum')
            ->postJson("/api/client/bookings/{$autreBooking->id}/onsite/extras/{$extra->id}/approve")
            ->assertNotFound();

        $this->assertTrue($extra->fresh()->estEnAttente());
    }

    #[Test]
    public function le_client_liste_ce_qui_attend_sa_reponse(): void
    {
        [$provider, $mission, $client, $booking] = $this->scenario();

        app(MissionExtraService::class)->propose($mission, $provider, 'Vitres', 2500);
        $repondu = app(MissionExtraService::class)->propose($mission, $provider, 'Four', 1500);
        app(MissionExtraService::class)->decline($repondu, $client);

        $reponse = $this->actingAs($client, 'sanctum')
            ->getJson("/api/client/bookings/{$booking->id}/onsite/extras")
            ->assertOk();

        // Les deux sont rendus — le refus se relit — mais un seul attend une réponse.
        $this->assertCount(2, $reponse->json('data'));
        $this->assertSame(
            1,
            collect($reponse->json('data'))->where('awaiting_client', true)->count(),
        );
    }
}

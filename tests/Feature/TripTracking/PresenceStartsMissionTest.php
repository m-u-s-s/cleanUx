<?php

namespace Tests\Feature\TripTracking;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\TripTrackingSession;
use App\Models\User;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * La présence prouvée démarre la mission.
 *
 * Le démarrage exigeait un code à six chiffres envoyé au client par SMS, que le prestataire
 * recopiait. Ce code faisait exactement le travail de la preuve de présence — et moins bien,
 * puisqu'un SMS se transmet à distance alors qu'un code affiché se lit sur place. Le faire
 * saisir une seconde fois n'apportait rien.
 *
 * Ce qui est verrouillé ici : le démarrage suit la confirmation, il ne peut pas écraser une
 * durée déjà en cours, et son échec ne remet jamais en cause la présence — celle-ci est un fait
 * acquis au moment où le prestataire est chez le client.
 */
class PresenceStartsMissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirming_presence_starts_the_mission(): void
    {
        Notification::fake();
        [$client, $provider, $booking, $session, $mission] = $this->scenario(MissionStatus::ARRIVED);

        $this->confirm($client, $provider, $booking, $session)
            ->assertOk()
            ->assertJsonPath('mission_started', true);

        $mission->refresh();
        $this->assertSame(MissionStatus::STARTED, $mission->status);
        $this->assertNotNull($mission->actual_start_at);
    }

    /** La présence sur place est gravée sur la mission, pas seulement sur la session. */
    public function test_the_mission_records_the_client_presence(): void
    {
        Notification::fake();
        [$client, $provider, $booking, $session, $mission] = $this->scenario(MissionStatus::ARRIVED);

        $this->confirm($client, $provider, $booking, $session)->assertOk();

        $this->assertTrue((bool) $mission->refresh()->client_presence_confirmed);
        $this->assertSame($provider->id, $mission->started_by_user_id);
    }

    /** Un prestataire qui n'a pas annoncé son arrivée peut malgré tout être devant la porte. */
    public function test_a_mission_still_en_route_is_started_too(): void
    {
        Notification::fake();
        [$client, $provider, $booking, $session, $mission] = $this->scenario(MissionStatus::EN_ROUTE);

        $this->confirm($client, $provider, $booking, $session)->assertOk();

        $this->assertSame(MissionStatus::STARTED, $mission->refresh()->status);
    }

    /**
     * Garantie centrale pour la facturation : `actual_start_at` alimente la durée facturée.
     * Rejouer la confirmation sur une mission déjà commencée la remettrait à l'heure du second
     * passage, raccourcissant la prestation.
     */
    public function test_a_started_mission_keeps_its_original_start_time(): void
    {
        Notification::fake();
        [$client, $provider, $booking, $session, $mission] = $this->scenario(MissionStatus::STARTED);
        $mission->forceFill(['actual_start_at' => now()->subHour()])->save();
        $original = $mission->fresh()->actual_start_at;

        $this->confirm($client, $provider, $booking, $session)
            ->assertOk()
            ->assertJsonPath('mission_started', false);

        $this->assertEquals($original, $mission->fresh()->actual_start_at);
    }

    /** La présence reste acquise quand il n'y a aucune mission à démarrer. */
    public function test_presence_holds_without_a_mission(): void
    {
        [$client, $provider, $booking, $session] = $this->scenario(null);

        $this->confirm($client, $provider, $booking, $session)
            ->assertOk()
            ->assertJsonPath('mission_started', false);

        $this->assertNotNull($session->fresh()->presence_confirmed_at);
    }

    /**
     * Le démarrage est un effet de bord. Il échoue quand le prestataire de la session de suivi
     * n'est rattaché à la mission ni comme responsable ni par une assignation — un remplacement
     * mal enregistré, par exemple. La présence, elle, a déjà eu lieu : elle doit le rester.
     */
    public function test_a_failing_start_never_undoes_the_presence(): void
    {
        Notification::fake();
        [$client, $provider, $booking, $session, $mission] = $this->scenario(MissionStatus::ARRIVED);

        $someoneElse = User::factory()->create(['role' => 'employe']);
        MissionAssignment::query()->where('mission_id', $mission->id)->delete();
        $mission->forceFill([
            'lead_employee_id' => $someoneElse->id,
            'lead_provider_user_id' => $someoneElse->id,
        ])->save();

        $this->confirm($client, $provider, $booking, $session)
            ->assertOk()
            ->assertJsonPath('mission_started', false);

        $this->assertNotNull($session->fresh()->presence_confirmed_at);
        $this->assertSame(MissionStatus::ARRIVED, $mission->fresh()->status);
    }

    /**
     * La mission part de la position VÉRIFIÉE, pas du dernier relevé reçu.
     *
     * `scenario()` place volontairement `last_lat`/`last_lng` sur des valeurs légèrement
     * différentes du scan : c'est la seule façon de distinguer les deux sources. Seule celle du
     * scan a été confrontée au lieu de l'intervention.
     */
    public function test_the_mission_starts_from_the_verified_position(): void
    {
        Notification::fake();
        [$client, $provider, $booking, $session, $mission] = $this->scenario(MissionStatus::ARRIVED);

        $this->confirm($client, $provider, $booking, $session)->assertOk();

        $mission->refresh();
        $this->assertEqualsWithDelta(self::SCAN_LAT, (float) $mission->start_lat, 0.00001);
        $this->assertEqualsWithDelta(self::SCAN_LNG, (float) $mission->start_lng, 0.00001);
    }

    /** Position relevée au moment du scan — celle que le serveur confronte au lieu de l'intervention. */
    private const SCAN_LAT = 50.8467;

    private const SCAN_LNG = 4.3525;

    private function confirm(User $client, User $provider, Booking $booking, TripTrackingSession $session)
    {
        $code = $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/presence-code")
            ->json('data.code');

        // La position accompagne la confirmation, comme le fait l'application : sans elle le
        // serveur refuse, et ces tests-ci passeraient pour une raison sans rapport avec la mission.
        return $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/tracking/{$session->id}/confirm-presence", [
                'code' => $code,
                'lat' => self::SCAN_LAT,
                'lng' => self::SCAN_LNG,
            ]);
    }

    /**
     * @return array{0: User, 1: User, 2: Booking, 3: TripTrackingSession, 4?: Mission}
     */
    private function scenario(?string $missionStatus): array
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->create(['role' => 'employe']);

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'status' => 'sur_place',
        ]);

        $session = TripTrackingSession::query()->create([
            'code' => TripTrackingSession::generateCode(),
            'booking_id' => $booking->id,
            'provider_user_id' => $provider->id,
            'status' => TripTrackingSession::STATUS_IN_MISSION,
            'destination_lat' => 50.8467,
            'destination_lng' => 4.3525,
            'last_lat' => 50.8466,
            'last_lng' => 4.3524,
            'started_at' => now()->subMinutes(30),
        ]);

        if ($missionStatus === null) {
            return [$client, $provider, $booking, $session];
        }

        /*
         * UNE RÉSERVATION, UNE MISSION — l'observateur en a déjà créé une.
         *
         * Les deux colonnes de `missions` étant fusionnées, le chemin automatique et celui du test
         * désignent enfin la même ligne. Créer ici en aveugle fabriquerait un doublon, et le code
         * qui cherche « la mission de cette réservation » trouverait la mauvaise.
         */
        $mission = Mission::query()->updateOrCreate(['booking_id' => $booking->id], [
            'status' => $missionStatus,
            'lead_provider_user_id' => $provider->id,
            'lead_employee_id' => $provider->id,
            'planned_start_at' => now()->subHour(),
        ]);

        MissionAssignment::query()->create([
            'mission_id' => $mission->id,
            'user_id' => $provider->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'accepted',
            'assigned_at' => now()->subHour(),
            'accepted_at' => now()->subHour(),
        ]);

        return [$client, $provider, $booking, $session, $mission];
    }
}

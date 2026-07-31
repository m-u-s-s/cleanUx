<?php

namespace Tests\Feature\TripTracking;

use App\Models\Booking;
use App\Models\TripTrackingSession;
use App\Models\User;
use App\Services\TripTracking\PresenceCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Le prestataire confirme sa présence avec le code que le client affiche.
 *
 * La géo-barrière atteste d'une proximité, pas d'une présence : un téléphone à 100 m de la porte
 * la franchit, et la session bascule seule en `arrived`. Confirmer réellement exige les deux
 * appareils au même endroit — d'où un code à usage unique, montré par le client et scanné par le
 * prestataire.
 *
 * Ce qui est verrouillé ici : le code ne quitte jamais la base en clair, il périme, il ne se
 * devine pas, et il n'est délivré qu'une fois l'intervention démarrée.
 *
 * Le croisement avec la position du scan est vérifié à part, dans {@see PresenceGeoProofTest}.
 * Ici, toutes les confirmations envoient une position valide — l'application le fait toujours, et
 * l'omettre ferait passer ces tests-ci pour une raison étrangère à ce qu'ils prétendent établir.
 */
class PresenceConfirmationTest extends TestCase
{
    use RefreshDatabase;

    /** Le lieu de l'intervention, tel que posé par `scenario()`. */
    private const SITE_LAT = 50.8467;

    private const SITE_LNG = 4.3525;

    public function test_the_client_gets_a_code_once_the_mission_has_started(): void
    {
        [$client, $provider, $booking, $session] = $this->scenario(TripTrackingSession::STATUS_IN_MISSION);

        $response = $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/presence-code")
            ->assertOk();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $response->json('data.code'));
        $this->assertSame($session->id, $response->json('data.session_id'));
    }

    /**
     * Garantie centrale : le code en clair ne doit exister que dans la réponse et sur l'écran du
     * client. Une base lisible ne doit pas suffire à confirmer une présence.
     */
    public function test_the_plain_code_is_never_stored(): void
    {
        [$client, , $booking, $session] = $this->scenario(TripTrackingSession::STATUS_IN_MISSION);

        $code = $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/presence-code")
            ->json('data.code');

        $session->refresh();
        $this->assertNotSame($code, $session->presence_code_hash);
        $this->assertTrue(Hash::check($code, $session->presence_code_hash));
    }

    /** L'empreinte ne doit jamais être sérialisée, y compris par du code qu'on n'a pas écrit. */
    public function test_the_hash_is_hidden_from_serialisation(): void
    {
        [$client, , $booking, $session] = $this->scenario(TripTrackingSession::STATUS_IN_MISSION);

        $this->actingAs($client, 'sanctum')->postJson("/api/client/bookings/{$booking->id}/presence-code");

        $this->assertArrayNotHasKey('presence_code_hash', $session->fresh()->toArray());
    }

    /** Avant le démarrage, il n'y a pas de présence à attester. */
    public function test_no_code_is_issued_before_the_mission_starts(): void
    {
        [$client, , $booking] = $this->scenario(TripTrackingSession::STATUS_ARRIVED);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/presence-code")
            ->assertStatus(409)
            ->assertJsonPath('error', 'not_in_mission');
    }

    public function test_another_client_cannot_get_the_code(): void
    {
        [, , $booking] = $this->scenario(TripTrackingSession::STATUS_IN_MISSION);
        $intruder = User::factory()->client()->create();

        $this->actingAs($intruder, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/presence-code")
            ->assertStatus(403);
    }

    public function test_the_provider_confirms_with_the_scanned_code(): void
    {
        [$client, $provider, $booking, $session] = $this->scenario(TripTrackingSession::STATUS_IN_MISSION);

        $code = $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/presence-code")
            ->json('data.code');

        $this->confirm($provider, $session, $code)
            ->assertOk()
            ->assertJsonPath('data.status', TripTrackingSession::STATUS_IN_MISSION);

        $this->assertNotNull($session->fresh()->presence_confirmed_at);
        $this->assertSame($provider->id, $session->fresh()->presence_confirmed_by_user_id);
    }

    /** Le code a servi : il ne doit plus pouvoir servir. */
    public function test_a_code_cannot_be_used_twice(): void
    {
        [$client, $provider, $booking, $session] = $this->scenario(TripTrackingSession::STATUS_IN_MISSION);

        $code = $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/presence-code")
            ->json('data.code');

        $this->confirm($provider, $session, $code)->assertOk();

        $this->assertNull($session->fresh()->presence_code_hash);
    }

    public function test_a_wrong_code_is_rejected(): void
    {
        [$client, $provider, $booking, $session] = $this->scenario(TripTrackingSession::STATUS_IN_MISSION);

        $this->actingAs($client, 'sanctum')->postJson("/api/client/bookings/{$booking->id}/presence-code");

        $this->confirm($provider, $session, '000000')->assertStatus(422);

        $this->assertNull($session->fresh()->presence_confirmed_at);
    }

    /**
     * Six chiffres se devinent en un million d'essais — rien pour une machine. Le plafond est
     * donc la seule chose qui rende le code utilisable.
     */
    public function test_repeated_guesses_burn_the_code(): void
    {
        [$client, $provider, $booking, $session] = $this->scenario(TripTrackingSession::STATUS_IN_MISSION);

        $code = $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/presence-code")
            ->json('data.code');

        for ($i = 0; $i < PresenceCodeService::MAX_ATTEMPTS; $i++) {
            $this->confirm($provider, $session, '000000')->assertStatus(422);
        }

        // Le bon code arrive trop tard : la série d'essais l'a déjà brûlé.
        $this->confirm($provider, $session, $code)->assertStatus(422);

        $this->assertNull($session->fresh()->presence_confirmed_at);
    }

    public function test_an_expired_code_is_rejected(): void
    {
        [$client, $provider, $booking, $session] = $this->scenario(TripTrackingSession::STATUS_IN_MISSION);

        $code = $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/presence-code")
            ->json('data.code');

        $this->travel(PresenceCodeService::TTL_MINUTES + 1)->minutes();

        $this->confirm($provider, $session, $code)->assertStatus(422);

        $this->assertNull($session->fresh()->presence_confirmed_at);
    }

    /** Une session n'appartient qu'à son prestataire : personne d'autre ne confirme à sa place. */
    public function test_another_provider_cannot_confirm(): void
    {
        [$client, , $booking, $session] = $this->scenario(TripTrackingSession::STATUS_IN_MISSION);
        $intruder = User::factory()->create(['role' => 'employe']);

        $code = $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/presence-code")
            ->json('data.code');

        $this->confirm($intruder, $session, $code)->assertStatus(403);
    }

    /** Le client interroge cet état périodiquement pour retirer le code de son écran. */
    public function test_the_tracking_payload_reports_the_confirmation(): void
    {
        [$client, $provider, $booking, $session] = $this->scenario(TripTrackingSession::STATUS_IN_MISSION);

        $this->actingAs($client, 'sanctum')
            ->getJson("/api/client/bookings/{$booking->id}/tracking")
            ->assertOk()
            ->assertJsonPath('data.presence_confirmed_at', null);

        $code = $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/presence-code")
            ->json('data.code');
        $this->confirm($provider, $session, $code);

        $this->assertNotNull(
            $this->actingAs($client, 'sanctum')
                ->getJson("/api/client/bookings/{$booking->id}/tracking")
                ->json('data.presence_confirmed_at')
        );
    }

    /** Une fois confirmée, la présence ne se redemande pas. */
    public function test_no_new_code_after_confirmation(): void
    {
        [$client, $provider, $booking, $session] = $this->scenario(TripTrackingSession::STATUS_IN_MISSION);

        $code = $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/presence-code")
            ->json('data.code');
        $this->confirm($provider, $session, $code);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/presence-code")
            ->assertStatus(409)
            ->assertJsonPath('error', 'already_confirmed');
    }

    /**
     * Confirme comme le fait l'application : avec la position relevée au moment du scan.
     *
     * @return TestResponse
     */
    private function confirm(User $provider, TripTrackingSession $session, string $code)
    {
        return $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/tracking/{$session->id}/confirm-presence", [
                'code' => $code,
                'lat' => self::SITE_LAT,
                'lng' => self::SITE_LNG,
            ]);
    }

    /**
     * @return array{0: User, 1: User, 2: Booking, 3: TripTrackingSession}
     */
    private function scenario(string $status): array
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
            'status' => $status,
            'destination_lat' => 50.8467,
            'destination_lng' => 4.3525,
            'started_at' => now()->subMinutes(20),
        ]);

        return [$client, $provider, $booking, $session];
    }
}

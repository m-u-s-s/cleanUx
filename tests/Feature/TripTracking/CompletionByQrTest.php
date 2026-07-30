<?php

namespace Tests\Feature\TripTracking;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionVerificationCode;
use App\Models\User;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Le client atteste la fin du travail avec un code que le prestataire scanne.
 *
 * Symétrique de la preuve de présence, à l'autre bout de la visite. Le code de fin était envoyé
 * au client par SMS puis recopié par le prestataire : un SMS voyage, alors qu'un code lu sur
 * l'écran du client exige les deux personnes dans la même pièce.
 *
 * L'enjeu est plus lourd qu'au démarrage : la clôture encaisse le paiement pré-autorisé.
 * L'accord du client doit donc être un geste délibéré, pas un message transféré.
 */
class CompletionByQrTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_client_gets_a_code_once_the_work_has_started(): void
    {
        [$client, , $booking] = $this->scenario(MissionStatus::STARTED);

        $response = $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/completion-code")
            ->assertOk();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $response->json('data.code'));
    }

    /** Rien à attester tant que le travail n'a pas commencé. */
    public function test_no_code_before_the_mission_starts(): void
    {
        [$client, , $booking] = $this->scenario(MissionStatus::ARRIVED);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/completion-code")
            ->assertStatus(409)
            ->assertJsonPath('error', 'not_started');
    }

    /** Le code en clair ne doit exister que dans la réponse et sur l'écran du client. */
    public function test_the_plain_code_is_never_stored(): void
    {
        [$client, , $booking, $mission] = $this->scenario(MissionStatus::STARTED);

        $code = $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/completion-code")
            ->json('data.code');

        $record = MissionVerificationCode::query()
            ->where('mission_id', $mission->id)
            ->where('code_type', 'end')
            ->latest('id')
            ->firstOrFail();

        $this->assertNotSame($code, $record->code_hash);
        $this->assertTrue(Hash::check($code, $record->code_hash));
    }

    public function test_another_client_cannot_get_the_code(): void
    {
        [, , $booking] = $this->scenario(MissionStatus::STARTED);
        $intruder = User::factory()->client()->create();

        $this->actingAs($intruder, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/completion-code")
            ->assertStatus(403);
    }

    public function test_the_provider_closes_the_mission_with_the_scanned_code(): void
    {
        Notification::fake();
        [$client, $provider, $booking, $mission] = $this->scenario(MissionStatus::STARTED);

        $code = $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/completion-code")
            ->json('data.code');

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/complete-by-qr", ['code' => $code])
            ->assertOk()
            ->assertJsonPath('status', MissionStatus::COMPLETED);

        $mission->refresh();
        $this->assertSame(MissionStatus::COMPLETED, $mission->status);
        $this->assertNotNull($mission->actual_end_at);
    }

    /**
     * Garantie centrale : la clôture encaisse. Un code refusé ne doit donc RIEN déclencher —
     * ni fin de mission, ni prélèvement.
     */
    public function test_a_wrong_code_closes_nothing(): void
    {
        Notification::fake();
        [$client, $provider, $booking, $mission] = $this->scenario(MissionStatus::STARTED);

        $this->actingAs($client, 'sanctum')->postJson("/api/client/bookings/{$booking->id}/completion-code");

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/complete-by-qr", ['code' => '000000'])
            ->assertStatus(422);

        $mission->refresh();
        $this->assertSame(MissionStatus::STARTED, $mission->status);
        $this->assertNull($mission->actual_end_at);
    }

    /** Un encaissement ne se rejoue pas : le code a servi, il ne sert plus. */
    public function test_a_code_cannot_close_twice(): void
    {
        Notification::fake();
        [$client, $provider, $booking, $mission] = $this->scenario(MissionStatus::STARTED);

        $code = $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/completion-code")
            ->json('data.code');

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/complete-by-qr", ['code' => $code])
            ->assertOk();

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/complete-by-qr", ['code' => $code])
            ->assertStatus(422);
    }

    /** Une mission n'appartient qu'à ceux qui y sont affectés. */
    public function test_another_provider_cannot_close_the_mission(): void
    {
        Notification::fake();
        [$client, , $booking, $mission] = $this->scenario(MissionStatus::STARTED);
        $intruder = User::factory()->create(['role' => 'employe']);

        $code = $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/completion-code")
            ->json('data.code');

        $this->actingAs($intruder, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/complete-by-qr", ['code' => $code])
            ->assertStatus(403);

        $this->assertSame(MissionStatus::STARTED, $mission->fresh()->status);
    }

    /**
     * @return array{0: User, 1: User, 2: Booking, 3: Mission}
     */
    private function scenario(string $missionStatus): array
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->create(['role' => 'employe']);

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'status' => 'sur_place',
        ]);

        $mission = Mission::query()->create([
            'booking_id' => $booking->id,
            'rendez_vous_id' => $booking->id,
            'status' => $missionStatus,
            'lead_provider_user_id' => $provider->id,
            'lead_employee_id' => $provider->id,
            'planned_start_at' => now()->subHours(2),
            'actual_start_at' => $missionStatus === MissionStatus::STARTED ? now()->subHour() : null,
        ]);

        MissionAssignment::query()->create([
            'mission_id' => $mission->id,
            'user_id' => $provider->id,
            'role' => 'lead',
            'role_on_mission' => 'lead',
            'status' => 'accepted',
            'assignment_status' => 'accepted',
            'assigned_at' => now()->subHours(2),
            'accepted_at' => now()->subHours(2),
        ]);

        return [$client, $provider, $booking, $mission];
    }
}

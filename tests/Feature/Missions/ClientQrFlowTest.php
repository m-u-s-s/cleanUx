<?php

namespace Tests\Feature\Missions;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionVerificationCode;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/** E1 — the mobile client app posts to /api/client/bookings/{id}/qr-start|qr-end. */
class ClientQrFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_start_route_exists_and_starts_mission_with_valid_code(): void
    {
        [$client, , $mission] = $this->makeMission('arrived');
        MissionVerificationCode::factory()->startCode()->create([
            'mission_id' => $mission->id,
            'code_hash' => Hash::make('123456'),
            'is_consumed' => false,
        ]);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$mission->booking_id}/qr-start", ['qr_code' => '123456'])
            ->assertOk()
            ->assertJsonPath('status', 'started');
    }

    public function test_qr_end_route_exists_and_completes_mission_with_valid_code(): void
    {
        [$client, , $mission] = $this->makeMission('started');
        MissionVerificationCode::factory()->endCode()->create([
            'mission_id' => $mission->id,
            'code_hash' => Hash::make('654321'),
            'is_consumed' => false,
        ]);

        // LES TÂCHES OBLIGATOIRES SONT COCHÉES D'ABORD, comme le ferait le prestataire.
        $mission->loadMissing('checklists.items');
        foreach ($mission->checklists->flatMap->items as $item) {
            $item->update(['status' => 'done']);
        }

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$mission->booking_id}/qr-end", ['qr_code' => '654321'])
            ->assertOk()
            ->assertJsonPath('status', 'completed');
    }

    public function test_qr_end_rejects_non_owner_client(): void
    {
        [, , $mission] = $this->makeMission('started');
        MissionVerificationCode::factory()->endCode()->create([
            'mission_id' => $mission->id,
            'code_hash' => Hash::make('654321'),
            'is_consumed' => false,
        ]);

        $stranger = User::factory()->client()->create();

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/client/bookings/{$mission->booking_id}/qr-end", ['qr_code' => '654321'])
            ->assertForbidden();

        $this->assertSame('started', $mission->fresh()->status);
    }

    public function test_qr_end_wrong_code_returns_422_not_500(): void
    {
        [$client, , $mission] = $this->makeMission('started');
        MissionVerificationCode::factory()->endCode()->create([
            'mission_id' => $mission->id,
            'code_hash' => Hash::make('654321'),
            'is_consumed' => false,
        ]);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$mission->booking_id}/qr-end", ['qr_code' => '000000'])
            ->assertStatus(422);

        $this->assertSame('started', $mission->fresh()->status);
    }

    /** @return array{0: User, 1: User, 2: Mission} */
    protected function makeMission(string $status): array
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $provider->id, 'status' => 'active']);

        $booking = Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'employe_id' => $provider->id,
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:00:00',
            'status' => 'confirme',
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
        ]);

        // L'observateur a déjà créé la mission de cette réservation confirmée : depuis la fusion
        // des deux colonnes, en créer une seconde ferait un doublon que les points d'entrée
        // résoudraient dans le mauvais sens.
        $mission = Mission::updateOrCreate(['booking_id' => $booking->id], [
            'lead_provider_user_id' => $provider->id,
            'status' => $status,
            'actual_start_at' => $status === 'started' ? now()->subHour() : null,
            'planned_start_at' => now()->subHour(),
        ]);

        // Même raison : la synchronisation a déjà posé l'assignation du chef, et le couple
        // (mission, personne) est unique en base.
        MissionAssignment::updateOrCreate(
            ['mission_id' => $mission->id, 'user_id' => $provider->id],
            ['assignment_status' => 'accepted', 'assigned_at' => now(), 'accepted_at' => now()],
        );

        return [$client, $provider, $mission];
    }
}

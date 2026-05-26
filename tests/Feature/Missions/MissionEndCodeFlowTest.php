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

class MissionEndCodeFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_requires_end_code_when_pending_code_exists(): void
    {
        [$provider, $mission] = $this->makeStartedMission();

        MissionVerificationCode::factory()->endCode()->create([
            'mission_id' => $mission->id,
            'is_consumed' => false,
        ]);

        $response = $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/complete");

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Le code de fin est requis pour clôturer cette mission.');
    }

    public function test_complete_rejects_wrong_end_code(): void
    {
        [$provider, $mission] = $this->makeStartedMission();

        MissionVerificationCode::factory()->endCode()->create([
            'mission_id' => $mission->id,
            'code_hash' => Hash::make('999999'),
            'is_consumed' => false,
        ]);

        $response = $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/complete", [
                'end_code' => '111111',
            ]);

        $response->assertServerError();
        $this->assertSame('started', $mission->fresh()->status);
    }

    public function test_complete_succeeds_with_valid_end_code(): void
    {
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('completeMission() requires employee_cost column absent in SQLite test schema');
        }

        [$provider, $mission] = $this->makeStartedMission();

        MissionVerificationCode::factory()->endCode()->create([
            'mission_id' => $mission->id,
            'code_hash' => Hash::make('123456'),
            'is_consumed' => false,
        ]);

        $response = $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/complete", [
                'end_code' => '123456',
            ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('status', 'completed');
    }

    public function test_complete_without_end_code_works_when_no_pending_code(): void
    {
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('completeMission() requires employee_cost column absent in SQLite test schema');
        }

        [$provider, $mission] = $this->makeStartedMission();

        $response = $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/complete");

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('status', 'completed');
    }

    protected function makeStartedMission(): array
    {
        $client = User::factory()->create();
        $provider = User::factory()->create();
        ProviderProfile::create([
            'user_id' => $provider->id,
            'status' => 'active',
        ]);

        $booking = Booking::create([
            'booking_reference' => 'CUX-' . strtoupper(Str::random(6)),
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:00:00',
            'status' => 'confirme',
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
        ]);

        $mission = Mission::create([
            'booking_id' => $booking->id,
            'lead_provider_user_id' => $provider->id,
            'status' => 'started',
            'actual_start_at' => now()->subHour(),
            'planned_start_at' => now()->subHour(),
        ]);

        MissionAssignment::factory()->accepted()->create([
            'mission_id' => $mission->id,
            'user_id' => $provider->id,
        ]);

        return [$provider, $mission];
    }
}

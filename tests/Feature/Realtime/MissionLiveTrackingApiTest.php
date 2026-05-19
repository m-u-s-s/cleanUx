<?php

namespace Tests\Feature\Realtime;

use App\Models\BroadcastEvent;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MissionLiveTrackingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.default' => 'null']);
    }

    public function test_push_position_requires_authentication(): void
    {
        $mission = Mission::factory()->create();

        $this->postJson("/api/provider/missions/{$mission->id}/live/position", [
            'lat' => 50.85,
            'lng' => 4.35,
        ])->assertStatus(401);
    }

    public function test_push_position_returns_403_for_unrelated_provider(): void
    {
        $stranger = User::factory()->employe()->create();
        $mission = Mission::factory()->create();

        Sanctum::actingAs($stranger);

        $this->postJson("/api/provider/missions/{$mission->id}/live/position", [
            'lat' => 50.85,
            'lng' => 4.35,
        ])->assertStatus(403);
    }

    public function test_push_position_succeeds_for_assigned_provider(): void
    {
        $provider = User::factory()->employe()->create();
        $mission = Mission::factory()->create(['lead_provider_user_id' => $provider->id]);

        Sanctum::actingAs($provider);

        $response = $this->postJson("/api/provider/missions/{$mission->id}/live/position", [
            'lat' => 50.85,
            'lng' => 4.35,
            'accuracy_m' => 12.5,
            'heading' => 90,
            'sequence' => 'ping-001',
        ]);

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'channel' => 'mission.' . $mission->id,
        ]);

        $this->assertSame(1, BroadcastEvent::query()
            ->forChannel('private-mission.' . $mission->id)
            ->forCategory(BroadcastEvent::CATEGORY_POSITION)
            ->count());
    }

    public function test_push_position_validates_lat_lng_ranges(): void
    {
        $provider = User::factory()->employe()->create();
        $mission = Mission::factory()->create(['lead_provider_user_id' => $provider->id]);

        Sanctum::actingAs($provider);

        $this->postJson("/api/provider/missions/{$mission->id}/live/position", [
            'lat' => 200,
            'lng' => 400,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['lat', 'lng']);
    }

    public function test_push_eta_succeeds_for_assigned_provider(): void
    {
        $provider = User::factory()->employe()->create();
        $mission = Mission::factory()->create(['lead_provider_user_id' => $provider->id]);

        Sanctum::actingAs($provider);

        $response = $this->postJson("/api/provider/missions/{$mission->id}/live/eta", [
            'eta_minutes' => 15,
            'lat' => 50.85,
            'lng' => 4.35,
            'sequence' => 'eta-001',
        ]);

        $response->assertOk();

        $row = BroadcastEvent::query()
            ->forChannel('private-mission.' . $mission->id)
            ->forCategory(BroadcastEvent::CATEGORY_MISSION_ETA)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('mission.eta', $row->broadcast_as);
        $this->assertSame(15, (int) $row->payload['eta_minutes']);
    }

    public function test_push_eta_with_same_sequence_is_idempotent(): void
    {
        $provider = User::factory()->employe()->create();
        $mission = Mission::factory()->create(['lead_provider_user_id' => $provider->id]);

        Sanctum::actingAs($provider);

        $this->postJson("/api/provider/missions/{$mission->id}/live/eta", [
            'eta_minutes' => 10,
            'sequence' => 'eta-dup',
        ])->assertOk();

        $this->postJson("/api/provider/missions/{$mission->id}/live/eta", [
            'eta_minutes' => 20,
            'sequence' => 'eta-dup',
        ])->assertOk();

        $this->assertSame(1, BroadcastEvent::query()
            ->forCategory(BroadcastEvent::CATEGORY_MISSION_ETA)
            ->count());
    }
}

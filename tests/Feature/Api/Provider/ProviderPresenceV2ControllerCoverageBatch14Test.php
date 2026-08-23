<?php

namespace Tests\Feature\Api\Provider;

use App\Models\ProviderPresence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Coverage batch 14 — drives App\Http\Controllers\Api\Provider\PresenceController (the v2 four-state controller) through its HTTP routes: - GET /api/provider/presence-v2 status() -> offline payload - POST /api/provider/presence-v2/online goOnline() 200 + validation 422 - POST /api/provider/presence-v2/heartbeat heartbeat() 200 online, 422 offline + validation 422 - POST /api/provider/presence-v2/busy goBusy() 200 - POST /api/provider/presence-v2/break goBreak() 200 - POST /api/provider/presence-v2/offline goOffline() 200 */
class ProviderPresenceV2ControllerCoverageBatch14Test extends TestCase
{
    use RefreshDatabase;

    private function makeProvider(): User
    {
        return User::factory()->employe()->create();
    }

    public function test_status_creates_offline_presence_and_returns_payload(): void
    {
        $provider = $this->makeProvider();
        Sanctum::actingAs($provider);

        $this->getJson('/api/provider/presence-v2')
            ->assertOk()
            ->assertJsonPath('data.status', ProviderPresence::STATUS_OFFLINE)
            ->assertJsonPath('data.is_active', false)
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'current_lat',
                    'current_lng',
                    'available_radius_km',
                    'heartbeat_at',
                    'last_online_at',
                    'online_minutes_today',
                    'online_minutes_week',
                    'is_active',
                ],
            ]);

        $this->assertDatabaseHas('provider_presence', [
            'provider_user_id' => $provider->id,
            'status' => ProviderPresence::STATUS_OFFLINE,
        ]);
    }

    public function test_go_online_sets_position_and_radius(): void
    {
        $provider = $this->makeProvider();
        Sanctum::actingAs($provider);

        $this->postJson('/api/provider/presence-v2/online', [
            'lat' => 50.85,
            'lng' => 4.35,
            'radius_km' => 15,
            'device_info' => 'iPhone 15 Pro',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', ProviderPresence::STATUS_ONLINE)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.available_radius_km', 15);

        $this->assertDatabaseHas('provider_presence', [
            'provider_user_id' => $provider->id,
            'status' => ProviderPresence::STATUS_ONLINE,
            'available_radius_km' => 15,
        ]);
    }

    public function test_go_online_rejects_out_of_range_latitude(): void
    {
        $provider = $this->makeProvider();
        Sanctum::actingAs($provider);

        $this->postJson('/api/provider/presence-v2/online', ['lat' => 200, 'lng' => 4.35])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lat']);
    }

    public function test_heartbeat_while_online_succeeds(): void
    {
        $provider = $this->makeProvider();
        Sanctum::actingAs($provider);

        $this->postJson('/api/provider/presence-v2/online', ['lat' => 50.0, 'lng' => 4.0])->assertOk();

        $this->postJson('/api/provider/presence-v2/heartbeat', ['lat' => 50.1, 'lng' => 4.1])
            ->assertOk()
            ->assertJsonPath('data.status', ProviderPresence::STATUS_ONLINE)
            ->assertJsonPath('data.is_active', true);
    }

    public function test_heartbeat_while_offline_returns_validation_failed(): void
    {
        $provider = $this->makeProvider();
        Sanctum::actingAs($provider);

        // Touch status first so a presence row exists in the offline state.
        $this->getJson('/api/provider/presence-v2')->assertOk();

        $this->postJson('/api/provider/presence-v2/heartbeat', ['lat' => 50.0, 'lng' => 4.0])
            ->assertStatus(422)
            ->assertJsonPath('error', 'validation_failed')
            ->assertJsonStructure(['error', 'errors' => ['status']]);
    }

    public function test_heartbeat_rejects_out_of_range_longitude(): void
    {
        $provider = $this->makeProvider();
        Sanctum::actingAs($provider);

        $this->postJson('/api/provider/presence-v2/heartbeat', ['lng' => 999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lng']);
    }

    public function test_go_busy_marks_provider_busy(): void
    {
        $provider = $this->makeProvider();
        Sanctum::actingAs($provider);

        $this->postJson('/api/provider/presence-v2/online', ['lat' => 50.0, 'lng' => 4.0])->assertOk();

        $this->postJson('/api/provider/presence-v2/busy')
            ->assertOk()
            ->assertJsonPath('data.status', ProviderPresence::STATUS_BUSY)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('provider_presence', [
            'provider_user_id' => $provider->id,
            'status' => ProviderPresence::STATUS_BUSY,
        ]);
    }

    public function test_go_busy_is_idempotent(): void
    {
        $provider = $this->makeProvider();
        Sanctum::actingAs($provider);

        $this->postJson('/api/provider/presence-v2/busy')->assertOk();

        $this->postJson('/api/provider/presence-v2/busy')
            ->assertOk()
            ->assertJsonPath('data.status', ProviderPresence::STATUS_BUSY);

        $this->assertSame(
            1,
            ProviderPresence::query()->where('provider_user_id', $provider->id)->count(),
        );
    }

    public function test_go_break_marks_provider_on_break(): void
    {
        $provider = $this->makeProvider();
        Sanctum::actingAs($provider);

        $this->postJson('/api/provider/presence-v2/online', ['lat' => 50.0, 'lng' => 4.0])->assertOk();

        $this->postJson('/api/provider/presence-v2/break')
            ->assertOk()
            ->assertJsonPath('data.status', ProviderPresence::STATUS_ON_BREAK)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('provider_presence', [
            'provider_user_id' => $provider->id,
            'status' => ProviderPresence::STATUS_ON_BREAK,
        ]);
    }

    public function test_go_offline_marks_provider_offline(): void
    {
        $provider = $this->makeProvider();
        Sanctum::actingAs($provider);

        $this->postJson('/api/provider/presence-v2/online', ['lat' => 50.0, 'lng' => 4.0])->assertOk();

        $this->postJson('/api/provider/presence-v2/offline')
            ->assertOk()
            ->assertJsonPath('data.status', ProviderPresence::STATUS_OFFLINE)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('provider_presence', [
            'provider_user_id' => $provider->id,
            'status' => ProviderPresence::STATUS_OFFLINE,
        ]);
    }
}

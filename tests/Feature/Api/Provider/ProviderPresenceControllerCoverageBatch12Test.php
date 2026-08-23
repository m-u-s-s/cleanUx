<?php

namespace Tests\Feature\Api\Provider;

use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Coverage batch 12 — drives App\Http\Controllers\Api\ProviderPresenceController through its HTTP routes: - POST /api/provider/presence/online (200, validation 422) - POST /api/provider/presence/offline (200) - POST /api/provider/presence/heartbeat (200 online, 409 offline) - GET /api/provider/presence/me (200, 404 no profile) - abortIfNotProvider guard (employe without ProviderProfile -> 403) */
class ProviderPresenceControllerCoverageBatch12Test extends TestCase
{
    use RefreshDatabase;

    private function makeProvider(): User
    {
        $user = User::factory()->employe()->create();

        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        return $user;
    }

    public function test_online_marks_provider_online_and_returns_payload(): void
    {
        $provider = $this->makeProvider();
        Sanctum::actingAs($provider);

        $r = $this->postJson('/api/provider/presence/online', [
            'lat' => 48.8566,
            'lng' => 2.3522,
            'accuracy_meters' => 12.5,
            'battery_level' => 80,
            'app_version' => '1.2.3',
        ]);

        $r->assertOk()
            ->assertJson(['ok' => true, 'is_online' => true]);
        $r->assertJsonPath('went_online_at', fn ($v) => $v !== null);

        $this->assertDatabaseHas('provider_profiles', [
            'user_id' => $provider->id,
            'is_online' => true,
        ]);
    }

    public function test_online_validates_required_coordinates(): void
    {
        $provider = $this->makeProvider();
        Sanctum::actingAs($provider);

        $this->postJson('/api/provider/presence/online', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lat', 'lng']);
    }

    public function test_offline_marks_provider_offline(): void
    {
        $provider = $this->makeProvider();
        Sanctum::actingAs($provider);

        $this->postJson('/api/provider/presence/online', ['lat' => 50.0, 'lng' => 4.0])->assertOk();

        $this->postJson('/api/provider/presence/offline')
            ->assertOk()
            ->assertJson(['ok' => true, 'is_online' => false]);

        $this->assertDatabaseHas('provider_profiles', [
            'user_id' => $provider->id,
            'is_online' => false,
        ]);
    }

    public function test_heartbeat_while_online_updates_location(): void
    {
        $provider = $this->makeProvider();
        Sanctum::actingAs($provider);

        $this->postJson('/api/provider/presence/online', ['lat' => 50.0, 'lng' => 4.0])->assertOk();

        $r = $this->postJson('/api/provider/presence/heartbeat', [
            'lat' => 50.1,
            'lng' => 4.1,
            'speed_kmh' => 22.0,
            'heading' => 90,
            'app_state' => 'foreground',
        ]);

        $r->assertOk()
            ->assertJson(['ok' => true, 'is_online' => true]);
        $r->assertJsonPath('last_heartbeat_at', fn ($v) => $v !== null);
    }

    public function test_heartbeat_while_offline_returns_conflict(): void
    {
        $provider = $this->makeProvider();
        Sanctum::actingAs($provider);

        $this->postJson('/api/provider/presence/heartbeat', ['lat' => 50.0, 'lng' => 4.0])
            ->assertStatus(409)
            ->assertJson(['ok' => false, 'is_online' => false]);
    }

    public function test_me_returns_current_presence_state(): void
    {
        $provider = $this->makeProvider();
        Sanctum::actingAs($provider);

        $this->getJson('/api/provider/presence/me')
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'is_online' => false,
            ])
            ->assertJsonStructure([
                'current_lat',
                'current_lng',
                'went_online_at',
                'last_heartbeat_at',
            ]);
    }

    public function test_guard_rejects_employe_without_provider_profile(): void
    {
        $user = User::factory()->employe()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/provider/presence/me')->assertStatus(403);
    }
}

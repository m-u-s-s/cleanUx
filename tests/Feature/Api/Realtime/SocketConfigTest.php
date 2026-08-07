<?php

namespace Tests\Feature\Api\Realtime;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Sprint 0 — Task 4 : /api/realtime/socket-config + Bearer broadcasting/auth.
 */
class SocketConfigTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────
    // socket-config endpoint
    // ──────────────────────────────────────────────────────

    public function test_returns_socket_config_for_authenticated_user(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'pk_test_123',
            'broadcasting.connections.reverb.options.host' => 'realtime.brio.test',
            'broadcasting.connections.reverb.options.port' => 443,
            'broadcasting.connections.reverb.options.scheme' => 'https',
        ]);

        Sanctum::actingAs(User::factory()->create());

        $r = $this->getJson('/api/realtime/socket-config');

        $r->assertOk()->assertJson([
            'driver' => 'reverb',
            'key' => 'pk_test_123',
            'host' => 'realtime.brio.test',
            'port' => 443,
            'scheme' => 'https',
            'auth_endpoint' => '/api/broadcasting/auth',
        ]);
    }

    public function test_socket_config_requires_auth(): void
    {
        $this->getJson('/api/realtime/socket-config')->assertUnauthorized();
    }

    public function test_socket_config_does_not_leak_secret(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.secret' => 'sk_super_secret',
            'broadcasting.connections.reverb.key' => 'pk_test_456',
            'broadcasting.connections.reverb.options.host' => 'realtime.brio.test',
        ]);

        Sanctum::actingAs(User::factory()->create());
        $r = $this->getJson('/api/realtime/socket-config');

        $r->assertOk();
        $this->assertStringNotContainsString('sk_super_secret', $r->getContent());
    }

    // ──────────────────────────────────────────────────────
    // broadcasting/auth — Bearer token (mobile)
    // ──────────────────────────────────────────────────────

    public function test_broadcasting_auth_accepts_bearer_token(): void
    {
        // Use the 'testing' driver so no real Reverb/Pusher connection is needed.
        // TestingBroadcaster honours routes/channels.php callbacks and returns
        // {auth: "testing:<socket_id>"} for private channels.
        config(['broadcasting.default' => 'testing']);

        $user = User::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;

        // private-user.{userId} — authorized when $user->id === $userId (see routes/channels.php)
        $r = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-user.'.$user->id,
            ]);

        $r->assertOk()->assertJsonStructure(['auth']);
    }

    public function test_broadcasting_auth_rejects_unauthenticated_request(): void
    {
        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-user.1',
        ])->assertUnauthorized();
    }
}

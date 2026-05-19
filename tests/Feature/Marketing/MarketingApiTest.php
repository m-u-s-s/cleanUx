<?php

namespace Tests\Feature\Marketing;

use App\Models\MarketingOptOut;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_preferences_endpoint_requires_auth(): void
    {
        $this->getJson('/api/client/marketing/preferences')->assertStatus(401);
    }

    public function test_preferences_endpoint_returns_default_state(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/client/marketing/preferences');

        $response->assertOk();
        $response->assertJson([
            'preferences' => [
                'email' => false,
                'sms' => false,
                'push' => false,
                'all' => false,
            ],
        ]);
    }

    public function test_opt_out_endpoint_persists_and_reflects_in_preferences(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/client/marketing/opt-out', [
            'channel' => 'email',
            'reason' => 'too many',
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('preferences.email'));
        $this->assertSame(1, MarketingOptOut::count());
    }

    public function test_opt_out_endpoint_validates_channel_enum(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/client/marketing/opt-out', [
            'channel' => 'whatsapp',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['channel']);
    }

    public function test_opt_in_endpoint_removes_opt_out(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);
        $this->postJson('/api/client/marketing/opt-out', ['channel' => 'email']);
        $this->assertSame(1, MarketingOptOut::count());

        $response = $this->postJson('/api/client/marketing/opt-in', ['channel' => 'email']);

        $response->assertOk();
        $this->assertFalse($response->json('preferences.email'));
        $this->assertSame(0, MarketingOptOut::count());
    }

    public function test_opt_out_endpoint_cross_user_isolation(): void
    {
        $alice = User::factory()->client()->create();
        $bob = User::factory()->client()->create();

        Sanctum::actingAs($alice);
        $this->postJson('/api/client/marketing/opt-out', ['channel' => 'email']);

        Sanctum::actingAs($bob);
        $response = $this->getJson('/api/client/marketing/preferences');

        $this->assertFalse($response->json('preferences.email'));
    }
}

<?php

namespace Tests\Feature\Client;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class ClientDashboardV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_renders_v2_mount_point_when_feature_active(): void
    {
        $user = User::factory()->create(['role' => 'client', 'is_active' => true]);
        Feature::for($user)->activate('client-mobile-v2');

        $response = $this->actingAs($user)->get(route('client.dashboard'));

        $response->assertOk();
        $response->assertSee('id="client-home-island"', false);
        $response->assertSee('data-props=', false);
    }

    public function test_renders_legacy_blade_when_feature_inactive(): void
    {
        $user = User::factory()->create(['role' => 'client', 'is_active' => true]);

        $response = $this->actingAs($user)->get(route('client.dashboard'));

        $response->assertOk();
        $response->assertDontSee('id="client-home-island"', false);
    }

    public function test_props_json_contains_user_name(): void
    {
        $user = User::factory()->create(['name' => 'Mohamed', 'role' => 'client', 'is_active' => true]);
        Feature::for($user)->activate('client-mobile-v2');

        $response = $this->actingAs($user)->get(route('client.dashboard'));

        // Name is JSON-encoded inside data-props attribute — check raw HTML
        $response->assertSee('Mohamed', false);
    }

    public function test_v2_response_does_not_include_legacy_chrome(): void
    {
        $user = User::factory()->create(['role' => 'client', 'is_active' => true]);
        Feature::for($user)->activate('client-mobile-v2');

        $response = $this->actingAs($user)->get(route('client.dashboard'));

        $response->assertOk();
        $response->assertSee('id="client-home-island"', false);

        // Legacy chrome (PWA install banner, cookie banner) Alpine markers should be absent
        $response->assertDontSee('pwaInstallPrompt()', false);
        $response->assertDontSee('cookieBanner()', false);
        $response->assertDontSee('x-data="cookieBanner', false);
    }
}

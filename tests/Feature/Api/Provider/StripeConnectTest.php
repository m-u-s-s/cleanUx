<?php

namespace Tests\Feature\Api\Provider;

use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Payments\StripeConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Sprint 0 — Task 3 : Provider Stripe Connect API
 *
 * Coverage :
 *   - GET  /api/provider/stripe-connect/status   (not onboarded + onboarded with mock)
 *   - POST /api/provider/stripe-connect/onboard  (mock)
 *   - GET  /api/provider/stripe-connect/payouts  (with + without stripe_account_id)
 *   - GET  /api/provider/stripe-connect/dashboard-link  (404 when no account)
 *   - 403 when caller is not a provider (client role)
 */
class StripeConnectTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    /**
     * Create a user with an associated ProviderProfile so the controller's
     * abortIfNotProvider check passes.
     */
    private function makeProvider(array $attributes = []): User
    {
        $user = User::factory()->employe()->create($attributes);

        ProviderProfile::create([
            'user_id'       => $user->id,
            'provider_type' => 'independent',
            'status'        => 'active',
        ]);

        return $user;
    }

    // ──────────────────────────────────────────────
    // 1. Status — not onboarded
    // ──────────────────────────────────────────────

    public function test_status_returns_not_onboarded_for_new_provider(): void
    {
        $provider = $this->makeProvider(['stripe_connect_account_id' => null]);
        Sanctum::actingAs($provider);

        $r = $this->getJson('/api/provider/stripe-connect/status');

        $r->assertOk()->assertJson([
            'onboarded'       => false,
            'charges_enabled' => false,
            'payouts_enabled' => false,
            'requirements'    => [],
        ]);
    }

    // ──────────────────────────────────────────────
    // 2. Status — onboarded (mocked Stripe call)
    // ──────────────────────────────────────────────

    public function test_status_returns_capabilities_for_onboarded_provider(): void
    {
        $provider = $this->makeProvider(['stripe_connect_account_id' => 'acct_test_xxx']);
        Sanctum::actingAs($provider);

        // Build a fake Stripe Account object
        $fakeAccount = new \stdClass();
        $fakeAccount->charges_enabled = true;
        $fakeAccount->payouts_enabled = true;
        $fakeAccount->requirements    = (object) ['currently_due' => []];
        $fakeAccount->capabilities    = null;

        $this->mock(StripeConnectService::class, function ($m) use ($fakeAccount) {
            $m->shouldReceive('retrieveAccount')
              ->once()
              ->with('acct_test_xxx')
              ->andReturn($fakeAccount);
        });

        $r = $this->getJson('/api/provider/stripe-connect/status');

        $r->assertOk()->assertJson([
            'onboarded'       => true,
            'charges_enabled' => true,
            'payouts_enabled' => true,
        ]);
    }

    // ──────────────────────────────────────────────
    // 3. Onboard — live Stripe (skipped)
    // ──────────────────────────────────────────────

    public function test_onboard_returns_account_link_url(): void
    {
        $this->markTestSkipped('Live Stripe call — skip unless STRIPE_SECRET_TEST set. Mock-based variant below.');
    }

    // ──────────────────────────────────────────────
    // 4. Onboard — mocked
    // ──────────────────────────────────────────────

    public function test_onboard_returns_account_link_url_with_mock(): void
    {
        $provider = $this->makeProvider(['stripe_connect_account_id' => null]);
        Sanctum::actingAs($provider);

        $fakeLink             = new \stdClass();
        $fakeLink->url        = 'https://connect.stripe.com/express/onboarding/abc';
        $fakeLink->expires_at = time() + 3600;

        $this->mock(StripeConnectService::class, function ($m) use ($fakeLink, $provider) {
            $m->shouldReceive('createExpressAccount')
              ->once()
              ->andReturnUsing(function (User $u) use ($provider) {
                  // Simulate the service storing the account id on the user
                  $u->update(['stripe_connect_account_id' => 'acct_test_new']);
                  return 'acct_test_new';
              });
            $m->shouldReceive('createAccountLink')
              ->once()
              ->andReturn($fakeLink);
        });

        $r = $this->postJson('/api/provider/stripe-connect/onboard');

        $r->assertOk()->assertJsonStructure(['url', 'expires_at']);
        $this->assertStringStartsWith('https://connect.stripe.com/', $r->json('url'));
        $this->assertEquals('acct_test_new', $provider->fresh()->stripe_connect_account_id);
    }

    // ──────────────────────────────────────────────
    // 5. Payouts — provider with account (mocked)
    // ──────────────────────────────────────────────

    public function test_payouts_returns_paginated_list_for_provider(): void
    {
        $provider = $this->makeProvider(['stripe_connect_account_id' => 'acct_test_xyz']);
        Sanctum::actingAs($provider);

        $fakeList           = new \stdClass();
        $fakeList->data     = [];
        $fakeList->has_more = false;

        $this->mock(StripeConnectService::class, function ($m) use ($fakeList) {
            $m->shouldReceive('listPayouts')
              ->once()
              ->andReturn($fakeList);
        });

        $r = $this->getJson('/api/provider/stripe-connect/payouts');

        $r->assertOk()->assertJsonStructure(['data', 'has_more']);
    }

    // ──────────────────────────────────────────────
    // 6. Payouts — provider without account
    // ──────────────────────────────────────────────

    public function test_payouts_returns_empty_for_provider_without_stripe_account(): void
    {
        $provider = $this->makeProvider(['stripe_connect_account_id' => null]);
        Sanctum::actingAs($provider);

        $r = $this->getJson('/api/provider/stripe-connect/payouts');

        $r->assertOk()->assertJson(['data' => [], 'has_more' => false]);
    }

    // ──────────────────────────────────────────────
    // 7. Dashboard link — no account → 404
    // ──────────────────────────────────────────────

    public function test_dashboard_link_requires_onboarded_provider(): void
    {
        $provider = $this->makeProvider(['stripe_connect_account_id' => null]);
        Sanctum::actingAs($provider);

        $r = $this->getJson('/api/provider/stripe-connect/dashboard-link');

        // Task 2's unified handler renders abort(404) as { ok: false, error_code: 'not_found' }
        $r->assertStatus(404)->assertJson(['ok' => false, 'error_code' => 'not_found']);
    }

    // ──────────────────────────────────────────────
    // 8. Non-provider (client role) → 403
    // ──────────────────────────────────────────────

    public function test_endpoints_require_provider_role(): void
    {
        $client = User::factory()->client()->create();
        Sanctum::actingAs($client);

        $this->getJson('/api/provider/stripe-connect/status')->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Spine;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Spine\SpineScenario;
use Tests\TestCase;

/**
 * F4 — Money-path role + organization isolation.
 *
 * Proves that no client/org can read or mutate another tenant's booking,
 * payment, payout, or tip via the real API routes.
 *
 * Discovery (Step 0) — money-path endpoints that accept a resource id:
 *
 * CLIENT side (prefix /api/client):
 *   GET    /api/client/bookings/{booking}                    ClientBookingController@show
 *   POST   /api/client/bookings/{booking}/cancel             ClientBookingController@cancel
 *   GET    /api/client/bookings/{booking}/eta                ClientBookingController@eta
 *   POST   /api/client/bookings/{booking}/payment-intent     BookingPaymentController@createPaymentIntent
 *   GET    /api/client/bookings/{booking}/tip/suggestions    TipController@suggestions
 *   POST   /api/client/bookings/{booking}/tip                TipController@create
 *   GET    /api/client/bookings/{booking}/cancellation-quote CancellationController@quote
 *   POST   /api/client/bookings/{booking}/cancel-with-fee    CancellationController@cancelWithFee
 *   GET    /api/client/bookings/{booking}/tracking           TripTrackingController@currentForBooking
 *
 * PROVIDER side:
 *   GET    /api/provider/missions/{mission}                  ProviderMissionLifecycleController@show
 *   POST   /api/provider/missions/{mission}/start            ProviderMissionLifecycleController@start
 *   GET    /api/provider/wallet/balance                      ProviderWalletController@balance     (user-scoped, no id)
 *   GET    /api/provider/payouts                             ProviderPayoutsController@index      (user-scoped, no id)
 *
 * Chosen for id-based cross-tenant tests (6 endpoints):
 *   1. GET  /api/client/bookings/{booking}
 *   2. POST /api/client/bookings/{booking}/cancel
 *   3. POST /api/client/bookings/{booking}/payment-intent
 *   4. GET  /api/client/bookings/{booking}/tip/suggestions
 *   5. GET  /api/client/bookings/{booking}/cancellation-quote
 *   6. GET  /api/provider/missions/{mission}
 */
class MoneyPathIsolationTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────
    // 1. Client B cannot GET client A's booking detail
    // ──────────────────────────────────────────────────────────────────────

    public function test_client_cannot_read_another_clients_booking(): void
    {
        $a = SpineScenario::make()->build();
        $b = SpineScenario::make()->build();

        Sanctum::actingAs($b->client);

        $response = $this->getJson("/api/client/bookings/{$a->booking->id}");

        $this->assertContains(
            $response->status(),
            [403, 404],
            "F4: client B got HTTP {$response->status()} on client A booking — cross-tenant read must be denied"
        );

        if ($response->status() === 200) {
            $this->fail('client B got 200 on client A booking — F4 IDOR leak (GET detail)');
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // 2. Client B cannot cancel client A's booking
    // ──────────────────────────────────────────────────────────────────────

    public function test_client_cannot_cancel_another_clients_booking(): void
    {
        $a = SpineScenario::make()->build();
        $b = SpineScenario::make()->build();

        Sanctum::actingAs($b->client);

        $response = $this->postJson("/api/client/bookings/{$a->booking->id}/cancel", [
            'reason' => 'test cross-tenant cancel attempt',
        ]);

        $this->assertContains(
            $response->status(),
            [403, 404],
            "F4: client B got HTTP {$response->status()} cancelling client A booking — must be denied"
        );

        if ($response->status() === 200) {
            $this->fail('client B got 200 cancelling client A booking — F4 IDOR leak (POST cancel)');
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // 3. Client B cannot create a payment-intent on client A's booking
    // ──────────────────────────────────────────────────────────────────────

    public function test_client_cannot_create_payment_intent_for_another_clients_booking(): void
    {
        $a = SpineScenario::make()->build();
        $b = SpineScenario::make()->build();

        Sanctum::actingAs($b->client);

        $response = $this->postJson("/api/client/bookings/{$a->booking->id}/payment-intent");

        $this->assertContains(
            $response->status(),
            [403, 404],
            "F4: client B got HTTP {$response->status()} creating payment-intent on client A booking — must be denied"
        );

        if ($response->status() === 200) {
            $this->fail('client B got 200 on payment-intent for client A booking — F4 IDOR leak (POST payment-intent)');
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // 4. Client B cannot read tip suggestions on client A's booking
    // ──────────────────────────────────────────────────────────────────────

    public function test_client_cannot_read_tip_suggestions_for_another_clients_booking(): void
    {
        $a = SpineScenario::make()->build();
        $b = SpineScenario::make()->build();

        Sanctum::actingAs($b->client);

        $response = $this->getJson("/api/client/bookings/{$a->booking->id}/tip/suggestions");

        $this->assertContains(
            $response->status(),
            [403, 404],
            "F4: client B got HTTP {$response->status()} on tip suggestions for client A booking — must be denied"
        );

        if ($response->status() === 200) {
            $this->fail('client B got 200 on tip suggestions for client A booking — F4 IDOR leak (GET tip/suggestions)');
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // 5. Client B cannot get a cancellation quote for client A's booking
    // ──────────────────────────────────────────────────────────────────────

    public function test_client_cannot_read_cancellation_quote_for_another_clients_booking(): void
    {
        $a = SpineScenario::make()->build();
        $b = SpineScenario::make()->build();

        Sanctum::actingAs($b->client);

        // F1 removal — the legacy /api/client/bookings/{id}/cancellation-quote is gone; clients
        // use the unified V2 endpoint, which now enforces booking ownership.
        $response = $this->getJson("/api/v2/client/bookings/{$a->booking->id}/cancellation-quote");

        $this->assertContains(
            $response->status(),
            [403, 404],
            "F4: client B got HTTP {$response->status()} on cancellation-quote for client A booking — must be denied"
        );

        if ($response->status() === 200) {
            $this->fail('client B got 200 on cancellation-quote for client A booking — F4 IDOR leak (GET cancellation-quote)');
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // 6. Provider B cannot read provider A's mission detail
    // ──────────────────────────────────────────────────────────────────────

    public function test_provider_cannot_read_another_providers_mission(): void
    {
        $a = SpineScenario::make()->build(); // mission belongs to $a->provider
        $b = SpineScenario::make()->build(); // $b->provider is a different tenant

        Sanctum::actingAs($b->provider);

        $response = $this->getJson("/api/provider/missions/{$a->mission->id}");

        $this->assertContains(
            $response->status(),
            [403, 404],
            "F4: provider B got HTTP {$response->status()} on provider A mission — must be denied"
        );

        if ($response->status() === 200) {
            $this->fail('provider B got 200 on provider A mission — F4 IDOR leak (GET provider/missions/{id})');
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // 7. A client cannot hit provider-only money endpoints
    // ──────────────────────────────────────────────────────────────────────

    public function test_client_cannot_access_provider_wallet_endpoints(): void
    {
        $a = SpineScenario::make()->build();

        // Act as the client (not the provider)
        Sanctum::actingAs($a->client);

        $walletResponse = $this->getJson('/api/provider/wallet/balance');
        $payoutsResponse = $this->getJson('/api/provider/payouts');

        // A client has no providerProfile, so the controller's abortIfNotProvider
        // guard must fire (403). If somehow 200 comes back, that is a role-boundary
        // leak (client seeing provider financial data).
        $this->assertContains(
            $walletResponse->status(),
            [401, 403, 404],
            "F4: client got HTTP {$walletResponse->status()} on /api/provider/wallet/balance — must be denied"
        );
        $this->assertContains(
            $payoutsResponse->status(),
            [401, 403, 404],
            "F4: client got HTTP {$payoutsResponse->status()} on /api/provider/payouts — must be denied"
        );

        if ($walletResponse->status() === 200) {
            $this->fail('client got 200 on /api/provider/wallet/balance — F4 role-boundary leak');
        }
        if ($payoutsResponse->status() === 200) {
            $this->fail('client got 200 on /api/provider/payouts — F4 role-boundary leak');
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // 8. A provider cannot access client-only booking endpoints
    // ──────────────────────────────────────────────────────────────────────

    public function test_provider_cannot_access_client_booking_endpoints(): void
    {
        $a = SpineScenario::make()->build();

        // Act as the provider trying to reach the CLIENT route
        Sanctum::actingAs($a->provider);

        $response = $this->getJson("/api/client/bookings/{$a->booking->id}");

        // The client endpoint checks client_id / customer_user_id ownership;
        // the provider is NOT the client → must be 403/404.
        $this->assertContains(
            $response->status(),
            [403, 404],
            "F4: provider got HTTP {$response->status()} on own booking via client endpoint — must be denied"
        );

        if ($response->status() === 200) {
            $this->fail('provider got 200 on client booking endpoint — F4 role-boundary leak (provider reading client route)');
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // 9. Cross-org parity surface (self-skip if routes not present on branch)
    // ──────────────────────────────────────────────────────────────────────

    public function test_parity_map_is_role_scoped_and_exposes_no_cross_tenant_data(): void
    {
        if (! Route::has('api.parity-map')) {
            $this->markTestSkipped('parity foundation not on this base — see spec base-branch note');
        }

        // The parity-map is a static, role-filtered module catalog (key/title/icon/path/
        // mobile from config) — it carries NO tenant-specific data. The correct isolation
        // property is therefore tenant-AGNOSTICISM: two clients from different orgs must
        // receive an identical module set (there is nothing cross-tenant to leak), and each
        // module must expose only static catalog fields, never tenant rows.
        $a = SpineScenario::make()->build();
        $b = SpineScenario::make()->build();

        Sanctum::actingAs($a->client);
        $aData = $this->getJson(route('api.parity-map'))->assertOk()->json('data');
        $aKeys = collect($aData)->pluck('key')->sort()->values()->all();

        Sanctum::actingAs($b->client);
        $bData = $this->getJson(route('api.parity-map'))->assertOk()->json('data');
        $bKeys = collect($bData)->pluck('key')->sort()->values()->all();

        $this->assertSame(
            $aKeys,
            $bKeys,
            'F4: parity-map must be tenant-agnostic (role-scoped) — different-tenant clients must see the identical module set'
        );

        // Toutes les fuites d'un coup : une carte qui expose un champ de trop l'expose sur
        // TOUTES ses entrees, et c'est le nombre qui dit l'ampleur.
        $attendues = ['key', 'title', 'icon', 'path', 'mobile'];
        $fuites = [];

        foreach ($aData as $module) {
            $clefs = array_keys($module);
            $enTrop = array_diff($clefs, $attendues);
            $absentes = array_diff($attendues, $clefs);

            if ($enTrop !== [] || $absentes !== []) {
                $fuites[] = sprintf(
                    '%s : en trop [%s], absentes [%s]',
                    $module['key'] ?? '?',
                    implode(', ', $enTrop),
                    implode(', ', $absentes),
                );
            }
        }

        $this->assertSame([], $fuites, 'La carte de parite doit n exposer que des champs de catalogue, jamais de donnee de locataire.');
    }

    public function test_webview_ticket_not_mintable_for_another_tenant(): void
    {
        if (! Route::has('api.auth.webview-ticket')) {
            $this->markTestSkipped('parity foundation not on this base — see spec base-branch note');
        }

        $a = SpineScenario::make()->build();
        $b = SpineScenario::make()->build();

        Sanctum::actingAs($b->client);

        // Attempt to mint a ticket targeting client A's admin path
        $response = $this->postJson(route('api.auth.webview-ticket'), [
            'target_user_id' => $a->client->id,
        ]);

        $this->assertContains(
            $response->status(),
            [401, 403, 404, 422],
            'F4: webview-ticket must not be mintable for another tenant'
        );
    }
}

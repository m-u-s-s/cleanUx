<?php

namespace Tests\Feature\Api\Client;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Sprint 0-bis — Payment API endpoints
 *
 * Coverage:
 *   - GET    /api/client/payment-methods          (list)
 *   - POST   /api/client/payment-methods/setup-intent
 *   - DELETE /api/client/payment-methods/{id}
 *   - POST   /api/client/bookings/{booking}/payment-intent
 *   - 401 when unauthenticated on all four endpoints
 */
class PaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────

    private function makeClient(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => 'client'], $attributes));
    }

    // ─────────────────────────────────────────
    // 1. Auth guard — all 4 endpoints return 401
    // ─────────────────────────────────────────

    public function test_endpoints_require_auth(): void
    {
        $this->getJson('/api/client/payment-methods')->assertUnauthorized();
        $this->postJson('/api/client/payment-methods/setup-intent')->assertUnauthorized();
        $this->deleteJson('/api/client/payment-methods/pm_test')->assertUnauthorized();
        $this->postJson('/api/client/bookings/1/payment-intent')->assertUnauthorized();
    }

    // ─────────────────────────────────────────
    // 2. List — user without stripe_id returns empty array
    // ─────────────────────────────────────────

    public function test_list_returns_empty_for_user_without_stripe_id(): void
    {
        Sanctum::actingAs($this->makeClient(['stripe_id' => null]));

        $this->getJson('/api/client/payment-methods')
            ->assertOk()
            ->assertJson(['data' => []]);
    }

    // ─────────────────────────────────────────
    // 3. SetupIntent — requires live Stripe key (skipped in CI)
    // ─────────────────────────────────────────

    public function test_setup_intent_requires_stripe_key(): void
    {
        $this->markTestSkipped('Requires a valid Stripe test key (cashier.secret).');
    }

    // ─────────────────────────────────────────
    // 4. DELETE — forbidden when user doesn't own payment method (stripe_id mismatch)
    // ─────────────────────────────────────────

    public function test_destroy_requires_stripe_key(): void
    {
        $this->markTestSkipped('Requires a valid Stripe test key for PaymentMethod::retrieve().');
    }

    // ─────────────────────────────────────────
    // 5. BookingPaymentIntent — booking not found → 404
    // ─────────────────────────────────────────

    public function test_payment_intent_returns_404_for_missing_booking(): void
    {
        Sanctum::actingAs($this->makeClient());

        $this->postJson('/api/client/bookings/999999/payment-intent')
            ->assertNotFound();
    }

    // ─────────────────────────────────────────
    // 6. BookingPaymentIntent — 403 when booking belongs to another client
    // ─────────────────────────────────────────

    public function test_payment_intent_is_forbidden_for_other_client_bookings(): void
    {
        $owner = $this->makeClient();
        $caller = $this->makeClient();

        $booking = Booking::factory()->create([
            'client_id' => $owner->id,
            'devis_estime' => 50.00,
        ]);

        Sanctum::actingAs($caller);

        $this->postJson("/api/client/bookings/{$booking->id}/payment-intent")
            ->assertForbidden();
    }

    // ─────────────────────────────────────────
    // 7. BookingPaymentIntent — 422 when booking has no price
    // ─────────────────────────────────────────

    public function test_payment_intent_rejects_zero_price_booking(): void
    {
        $client = $this->makeClient();

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'devis_estime' => 0,
        ]);

        Sanctum::actingAs($client);

        $this->postJson("/api/client/bookings/{$booking->id}/payment-intent")
            ->assertUnprocessable();
    }

    // ─────────────────────────────────────────
    // 8. BookingPaymentIntent — requires live Stripe (skipped in CI)
    // ─────────────────────────────────────────

    public function test_payment_intent_requires_stripe_key(): void
    {
        $this->markTestSkipped('Requires a valid Stripe test key for PaymentIntent::create().');
    }
}

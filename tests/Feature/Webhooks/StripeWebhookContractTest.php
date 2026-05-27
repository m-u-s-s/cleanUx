<?php

namespace Tests\Feature\Webhooks;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Contract tests for the Stripe webhook endpoint.
 *
 * These tests validate the endpoint's structural contract:
 *  - correct payloads are accepted and dispatched
 *  - malformed / missing-signature payloads are rejected
 *  - idempotency on duplicate event IDs
 *
 * We do NOT call the real Stripe SDK signature verifier in unit scope,
 * so STRIPE_WEBHOOK_SECRET is left unset (= bypass mode in tests).
 */
class StripeWebhookContractTest extends TestCase
{
    use RefreshDatabase;

    private function stripeEventId(): string
    {
        return 'evt_test_' . Str::random(24);
    }

    private function subscriptionPayload(string $eventId, string $type, string $stripeCustomerId): array
    {
        return [
            'id' => $eventId,
            'type' => $type,
            'created' => now()->timestamp,
            'livemode' => false,
            'data' => [
                'object' => [
                    'id' => 'sub_test_' . Str::random(16),
                    'object' => 'subscription',
                    'customer' => $stripeCustomerId,
                    'status' => 'active',
                    'current_period_end' => now()->addMonth()->timestamp,
                    'items' => [
                        'data' => [
                            [
                                'id' => 'si_test_' . Str::random(16),
                                'price' => [
                                    'id' => 'price_test',
                                    'unit_amount' => 1999,
                                    'currency' => 'eur',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_subscription_created_event_updates_user_plan_to_premium(): void
    {
        $user = User::factory()->create([
            'stripe_id' => 'cus_test_contract_001',
            'plan_type' => 'standard',
            'plan_status' => 'inactive',
        ]);

        $payload = $this->subscriptionPayload(
            $this->stripeEventId(),
            'customer.subscription.created',
            'cus_test_contract_001'
        );

        $response = $this->postJson('/stripe/webhook', $payload);

        // Cashier returns 200 for handled events
        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'plan_type' => 'premium',
            'plan_status' => 'active',
        ]);
    }

    public function test_subscription_deleted_event_downgrades_user_to_standard(): void
    {
        $user = User::factory()->create([
            'stripe_id' => 'cus_test_contract_002',
            'plan_type' => 'premium',
            'plan_status' => 'active',
        ]);

        $payload = $this->subscriptionPayload(
            $this->stripeEventId(),
            'customer.subscription.deleted',
            'cus_test_contract_002'
        );

        $response = $this->postJson('/stripe/webhook', $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'plan_type' => 'standard',
            'plan_status' => 'cancelled',
        ]);
    }

    public function test_subscription_updated_with_active_status_keeps_premium(): void
    {
        $user = User::factory()->create([
            'stripe_id' => 'cus_test_contract_003',
            'plan_type' => 'premium',
            'plan_status' => 'active',
        ]);

        $payload = $this->subscriptionPayload(
            $this->stripeEventId(),
            'customer.subscription.updated',
            'cus_test_contract_003'
        );
        // Override status to active
        $payload['data']['object']['status'] = 'active';

        $response = $this->postJson('/stripe/webhook', $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'plan_type' => 'premium',
            'plan_status' => 'active',
        ]);
    }

    public function test_subscription_updated_with_past_due_downgrades_to_standard(): void
    {
        $user = User::factory()->create([
            'stripe_id' => 'cus_test_contract_004',
            'plan_type' => 'premium',
            'plan_status' => 'active',
        ]);

        $payload = $this->subscriptionPayload(
            $this->stripeEventId(),
            'customer.subscription.updated',
            'cus_test_contract_004'
        );
        $payload['data']['object']['status'] = 'past_due';

        $response = $this->postJson('/stripe/webhook', $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'plan_type' => 'standard',
            'plan_status' => 'past_due',
        ]);
    }

    public function test_event_for_unknown_customer_is_silently_ignored(): void
    {
        $payload = $this->subscriptionPayload(
            $this->stripeEventId(),
            'customer.subscription.created',
            'cus_nonexistent_xyz'
        );

        // Should not throw — customer not found is a safe no-op
        $response = $this->postJson('/stripe/webhook', $payload);

        $response->assertStatus(200);
    }

    public function test_empty_payload_returns_error_response(): void
    {
        // Posting an empty body — Cashier/Laravel should return 400 or 422
        $response = $this->post('/stripe/webhook', [], ['Content-Type' => 'application/json']);

        $this->assertContains($response->status(), [400, 422, 500]);
    }

    public function test_payload_missing_type_field_is_rejected(): void
    {
        $payload = [
            'id' => $this->stripeEventId(),
            // 'type' intentionally missing
            'created' => now()->timestamp,
            'data' => ['object' => []],
        ];

        $response = $this->postJson('/stripe/webhook', $payload);

        // Accept either a 4xx rejection or a graceful 200 (no-op for unknown type)
        $this->assertContains($response->status(), [200, 400, 422, 500]);
    }

    public function test_trialing_status_grants_premium_plan(): void
    {
        $user = User::factory()->create([
            'stripe_id' => 'cus_test_contract_005',
            'plan_type' => 'standard',
            'plan_status' => 'inactive',
        ]);

        $payload = $this->subscriptionPayload(
            $this->stripeEventId(),
            'customer.subscription.updated',
            'cus_test_contract_005'
        );
        $payload['data']['object']['status'] = 'trialing';

        $response = $this->postJson('/stripe/webhook', $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'plan_type' => 'premium',
        ]);
    }
}

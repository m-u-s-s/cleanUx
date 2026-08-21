<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\Booking;
use App\Models\BookingInsurance;
use App\Models\InsuranceClaim;
use App\Models\InsurancePlan;
use App\Models\MarketingCampaign;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\Services\AccountingV2\PeriodCloser;
use App\Services\Insurance\InsuranceService;
use App\Services\WebhooksV2\WebhookDeliveryRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * SkeletalModulesTest — covers the 5 completed modules:
 *   Marketing (admin CRUD campaigns/segments)
 *   Fleet (provider my-vehicles endpoint)
 *   Accounting (PeriodCloser::canClose + validate endpoint)
 *   Insurance (updateClaimStatus state machine + client claims list)
 *   Webhooks (markDeadLetter + admin dead-letter endpoint)
 */
class SkeletalModulesTest extends TestCase
{
    use RefreshDatabase;

    // ─── Marketing ────────────────────────────────────────────────────────────

    public function test_marketing_campaigns_list(): void
    {
        $admin = User::factory()->adminComplet()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/marketing/campaigns')->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_marketing_campaigns_create(): void
    {
        $admin = User::factory()->adminComplet()->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/marketing/campaigns', [
            'name' => 'Test Campaign',
            'type' => 'single_blast',
        ])->assertStatus(201)
            ->assertJsonPath('data.name', 'Test Campaign');
    }

    public function test_marketing_campaigns_update(): void
    {
        $admin = User::factory()->adminComplet()->create();
        Sanctum::actingAs($admin);

        $campaign = MarketingCampaign::create([
            'code' => 'TEST_'.uniqid(),
            'name' => 'Old Name',
            'type' => 'single_blast',
            'status' => 'draft',
        ]);

        $this->putJson("/api/admin/marketing/campaigns/{$campaign->id}", [
            'name' => 'New Name',
        ])->assertOk()
            ->assertJsonPath('data.name', 'New Name');
    }

    public function test_marketing_campaigns_delete(): void
    {
        $admin = User::factory()->adminComplet()->create();
        Sanctum::actingAs($admin);

        $campaign = MarketingCampaign::create([
            'code' => 'DEL_'.uniqid(),
            'name' => 'To Delete',
            'type' => 'single_blast',
            'status' => 'draft',
        ]);

        $this->deleteJson("/api/admin/marketing/campaigns/{$campaign->id}")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('marketing_campaigns', ['id' => $campaign->id]);
    }

    public function test_marketing_segments_list(): void
    {
        $admin = User::factory()->adminComplet()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/marketing/segments')->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_marketing_segments_create(): void
    {
        $admin = User::factory()->adminComplet()->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/marketing/segments', [
            'name' => 'Active Clients',
            'rules' => ['role' => 'client', 'status' => 'active'],
        ])->assertStatus(201)
            ->assertJsonPath('data.name', 'Active Clients');
    }

    public function test_marketing_campaigns_require_auth(): void
    {
        $this->getJson('/api/admin/marketing/campaigns')->assertUnauthorized();
    }

    // ─── Fleet ────────────────────────────────────────────────────────────────

    public function test_fleet_my_vehicles_endpoint_returns_empty_for_new_provider(): void
    {
        $provider = User::factory()->create(['role' => 'employe']);
        Sanctum::actingAs($provider);

        $this->getJson('/api/provider/fleet/my-vehicles')
            ->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonCount(0, 'data');
    }

    public function test_fleet_my_vehicles_requires_auth(): void
    {
        $this->getJson('/api/provider/fleet/my-vehicles')->assertUnauthorized();
    }

    // ─── Accounting ───────────────────────────────────────────────────────────

    public function test_period_closer_can_close_balanced_period(): void
    {
        $period = AccountingPeriod::create([
            'period_year' => 2025,
            'period_month' => 1,
            'is_closed' => false,
            'opened_at' => now(),
        ]);

        // No entries → debit=0 = credit=0 → balanced
        $result = app(PeriodCloser::class)->canClose($period);

        $this->assertTrue($result['can_close']);
        $this->assertEmpty($result['errors']);
        $this->assertSame(0, $result['total_debit']);
        $this->assertSame(0, $result['total_credit']);
    }

    public function test_period_closer_rejects_already_closed_period(): void
    {
        $period = AccountingPeriod::create([
            'period_year' => 2025,
            'period_month' => 2,
            'is_closed' => true,
            'opened_at' => now(),
            'closed_at' => now(),
        ]);

        $result = app(PeriodCloser::class)->canClose($period);

        $this->assertFalse($result['can_close']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_accounting_period_validate_endpoint(): void
    {
        $admin = User::factory()->adminComplet()->create();
        Sanctum::actingAs($admin);

        $period = AccountingPeriod::create([
            'period_year' => 2025,
            'period_month' => 3,
            'is_closed' => false,
            'opened_at' => now(),
        ]);

        $this->getJson("/api/admin/accounting-v2/periods/{$period->id}/validate")
            ->assertOk()
            ->assertJsonStructure(['can_close', 'errors', 'total_debit', 'total_credit', 'entry_count']);
    }

    // ─── Insurance ────────────────────────────────────────────────────────────

    public function test_insurance_update_claim_status_valid_transition(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $plan = InsurancePlan::create([
            'code' => 'PLAN_TEST_'.uniqid(),
            'name' => 'Test Plan',
            'coverage_amount_cents' => 100000,
            'premium_base_cents' => 2000,
            'currency' => 'EUR',
            'is_active' => true,
        ]);
        $insurance = BookingInsurance::create([
            // Une VRAIE réservation : `booking_insurances.booking_id` porte désormais une clé
            // étrangère. L'identifiant « 1 » ne désignait aucune ligne.
            'booking_id' => Booking::factory()->create()->id,
            'plan_id' => $plan->id,
            'user_id' => $user->id,
            'premium_cents' => 2000,
            'coverage_amount_cents' => 100000,
            'currency' => 'EUR',
            'status' => 'active',
            'external_provider' => 'mock',
            'idempotency_key' => 'test_'.uniqid(),
            'purchased_at' => now(),
            'effective_from' => now(),
            'effective_until' => now()->addYear(),
        ]);
        $claim = InsuranceClaim::create([
            'booking_insurance_id' => $insurance->id,
            'claimant_user_id' => $user->id,
            'status' => InsuranceClaim::STATUS_FILED,
            'incident_type' => 'damage',
            'incident_description' => 'Test damage',
            'incident_date' => now()->subDay(),
            'amount_claimed_cents' => 5000,
            'filed_at' => now(),
            'idempotency_key' => 'claim_'.uniqid(),
        ]);

        /** @var InsuranceService $svc */
        $svc = app(InsuranceService::class);
        $updated = $svc->updateClaimStatus($claim, InsuranceClaim::STATUS_UNDER_REVIEW, 'En cours de révision');

        $this->assertSame(InsuranceClaim::STATUS_UNDER_REVIEW, $updated->status);
    }

    public function test_insurance_update_claim_status_invalid_transition_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $user = User::factory()->create(['role' => 'client']);
        $plan = InsurancePlan::create([
            'code' => 'PLAN_T2_'.uniqid(),
            'name' => 'Plan2',
            'coverage_amount_cents' => 50000,
            'premium_base_cents' => 1000,
            'currency' => 'EUR',
            'is_active' => true,
        ]);
        $insurance = BookingInsurance::create([
            'booking_id' => Booking::factory()->create()->id,
            'plan_id' => $plan->id,
            'user_id' => $user->id,
            'premium_cents' => 1000,
            'coverage_amount_cents' => 50000,
            'currency' => 'EUR',
            'status' => 'active',
            'external_provider' => 'mock',
            'idempotency_key' => 'test2_'.uniqid(),
            'purchased_at' => now(),
            'effective_from' => now(),
            'effective_until' => now()->addYear(),
        ]);
        $claim = InsuranceClaim::create([
            'booking_insurance_id' => $insurance->id,
            'claimant_user_id' => $user->id,
            'status' => InsuranceClaim::STATUS_FILED,
            'incident_type' => 'theft',
            'incident_description' => 'Stolen',
            'incident_date' => now()->subDay(),
            'amount_claimed_cents' => 3000,
            'filed_at' => now(),
            'idempotency_key' => 'claim2_'.uniqid(),
        ]);

        // filed → paid is an invalid transition
        app(InsuranceService::class)->updateClaimStatus($claim, InsuranceClaim::STATUS_PAID);
    }

    public function test_insurance_claims_client_endpoint(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        Sanctum::actingAs($client);

        $this->getJson('/api/client/insurance/claims')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_insurance_admin_claims_list(): void
    {
        $admin = User::factory()->adminComplet()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/insurance-v2/claims')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    // ─── Webhooks ─────────────────────────────────────────────────────────────

    public function test_webhook_delivery_runner_mark_dead_letter(): void
    {
        $endpoint = WebhookEndpoint::create([
            'code' => 'whe_test1_'.uniqid(),
            'name' => 'Test Endpoint 1',
            'url' => 'https://example.com/hook',
            'secret' => 'sec_test',
            'is_active' => true,
            'is_suspended' => false,
            'consecutive_failures' => 0,
            'timeout_seconds' => 10,
        ]);
        $event = WebhookEvent::create([
            'event_id' => 'evt_'.uniqid(),
            'event_code' => 'booking.created',
            'occurred_at' => now(),
            'payload' => ['test' => true],
        ]);
        $delivery = WebhookDelivery::create([
            'event_id' => $event->id,
            'endpoint_id' => $endpoint->id,
            'status' => WebhookDelivery::STATUS_FAILED,
            'attempt' => 5,
            'max_attempts' => 5,
        ]);

        $runner = app(WebhookDeliveryRunner::class);
        $runner->markDeadLetter($delivery);

        $this->assertSame(WebhookDelivery::STATUS_DEAD, $delivery->fresh()->status);
    }

    public function test_webhook_dead_letter_idempotent(): void
    {
        $endpoint = WebhookEndpoint::create([
            'code' => 'whe_test2_'.uniqid(),
            'name' => 'Test Endpoint 2',
            'url' => 'https://example.com/hook2',
            'secret' => 'sec_test2',
            'is_active' => true,
            'is_suspended' => false,
            'consecutive_failures' => 0,
            'timeout_seconds' => 10,
        ]);
        $event = WebhookEvent::create([
            'event_id' => 'evt_idem_'.uniqid(),
            'event_code' => 'booking.updated',
            'occurred_at' => now(),
            'payload' => [],
        ]);
        $delivery = WebhookDelivery::create([
            'event_id' => $event->id,
            'endpoint_id' => $endpoint->id,
            'status' => WebhookDelivery::STATUS_DEAD,
            'attempt' => 3,
            'max_attempts' => 3,
        ]);

        $runner = app(WebhookDeliveryRunner::class);
        $runner->markDeadLetter($delivery); // already dead — must not error

        $this->assertSame(WebhookDelivery::STATUS_DEAD, $delivery->fresh()->status);
    }

    public function test_admin_webhooks_endpoints_list(): void
    {
        $admin = User::factory()->adminComplet()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/webhooks-v2/endpoints')->assertOk();
    }
}

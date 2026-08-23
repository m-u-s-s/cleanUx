<?php

namespace Tests\Feature\Spine;

use App\Models\MissionAssignment;
use App\Models\ProviderWalletTransaction;
use App\Models\StripeWebhookEvent;
use App\Services\Missions\MissionLifecycleService;
use App\Services\Missions\MissionReportService;
use App\Services\Payments\MissionPaymentService;
use App\Services\Payments\Webhooks\StripeWebhookEventProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\ApiRequestor;
use Stripe\Stripe;
use Tests\Support\Spine\SpineScenario;
use Tests\Support\Stripe\FakeStripeHttpClient;
use Tests\Support\Stripe\StripeFakeResponses;
use Tests\TestCase;

/** Regression: a mission completed via MissionLifecycleService::completeMission followed by a payment_intent.succeeded webhook must credit the provider wallet EXACTLY ONCE, not twice. */
class SingleCreditGuaranteeTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeHttpClient $stripe;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cashier.secret' => 'sk_test_fake', 'services.stripe.secret' => 'sk_test_fake']);
        Stripe::setApiKey('sk_test_fake');
        $this->stripe = new FakeStripeHttpClient;
        ApiRequestor::setHttpClient($this->stripe);

        // MissionReportService::generate uses DomPDF + Storage; mock it so
        // completeMission() does not require a PDF renderer in the test suite.
        $this->mock(MissionReportService::class, function ($mock) {
            $mock->shouldReceive('generate')->andReturn('reports/mission-test.pdf');
        });
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    public function test_complete_mission_then_webhook_credits_wallet_exactly_once(): void
    {
        // ── 1. Build scenario ─────────────────────────────────────────────────
        $s = SpineScenario::make()->withDevis(100.00)->build();
        $piId = 'pi_single_credit_1';

        // ── 2. Authorize (pre-auth stub) ──────────────────────────────────────
        $this->stripe->stub(
            'POST',
            '/v1/payment_intents',
            StripeFakeResponses::paymentIntent($piId, 'requires_capture')
        );
        app(MissionPaymentService::class)->authorize($s->booking, 'pm_card_visa');
        $s->booking->refresh();
        $this->assertSame('authorized', $s->booking->payment_status, 'Pre-condition: booking must be authorized before completeMission');

        // ── 3. Stubs for MissionPaymentService::capture inside completeMission ─
        // capture() calls PaymentIntent::retrieve (GET) then $intent->capture() (POST).
        // Do NOT register a GET stub here — the authorize stub above already stored
        // the requires_capture PI body in FakeStripeHttpClient::lastKnown, so retrieve()
        // will fall through to lastKnown. After capture, lastKnown is updated with the
        // succeeded PI body, so syncPaymentIntent (called by the webhook handler)
        // also correctly reads 'succeeded' → 'captured'.
        $this->stripe->stub(
            'POST',
            "/v1/payment_intents/{$piId}/capture",
            StripeFakeResponses::paymentIntent($piId, 'succeeded')
        );

        // ── 4. Wire assignment so assertAssignedToMission passes ──────────────
        // completeMission → assertAssignedToMission checks lead_employee_id OR
        // mission_assignments. SpineScenario uses lead_provider_user_id, not
        // lead_employee_id, so we create a MissionAssignment row.
        MissionAssignment::create([
            'mission_id' => $s->mission->id,
            'user_id' => $s->provider->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'accepted',
            'assigned_at' => now(),
        ]);

        // ── 5. Call completeMission (the first credit path) ───────────────────
        $mission = $s->mission->fresh();
        app(MissionLifecycleService::class)->completeMission($mission, $s->provider);

        $s->booking->refresh();
        $this->assertSame('captured', $s->booking->payment_status, 'completeMission must capture the payment');

        // After completeMission alone, exactly one earning row must exist.
        // Before the fix: the raw insert fails silently (direction is NOT NULL and
        // not set by the schema-defensive code), so this assertion fails → proves the bug.
        $earningCount = ProviderWalletTransaction::where('provider_user_id', $s->provider->id)
            ->where('type', ProviderWalletTransaction::TYPE_EARNING)
            ->count();
        $this->assertSame(1, $earningCount, 'completeMission alone must produce exactly 1 earning credit (not 0, not 2)');

        // ── 6. Fire payment_intent.succeeded webhook (the second credit path) ─
        // The fake client's lastKnown already holds the succeeded PI body after
        // the capture stub above (rememberLastKnown strips the /capture suffix).
        $event = StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_single_credit_1',
            'type' => 'payment_intent.succeeded',
            'status' => StripeWebhookEvent::STATUS_RECEIVED,
            'payload' => [
                'data' => [
                    'object' => StripeFakeResponses::paymentIntent($piId, 'succeeded'),
                ],
            ],
            'received_at' => now(),
        ]);
        app(StripeWebhookEventProcessor::class)->process($event);

        // ── 7. Assert EXACTLY ONE earning row (dedup guarantee) ──────────────
        $finalCount = ProviderWalletTransaction::where('provider_user_id', $s->provider->id)
            ->where('type', ProviderWalletTransaction::TYPE_EARNING)
            ->count();
        $this->assertSame(1, $finalCount, 'completeMission + webhook must produce exactly 1 earning credit (no double-credit)');

        // ── 8. Assert correct amount ───────────────────────────────────────────
        $credit = ProviderWalletTransaction::where('provider_user_id', $s->provider->id)
            ->where('type', ProviderWalletTransaction::TYPE_EARNING)
            ->first();
        $this->assertNotNull($credit, 'earning transaction must exist');
        $this->assertEqualsWithDelta(80.00, (float) $credit->amount, 0.01, 'earning must be 80.00 (100 - 20% fee)');
        $this->assertSame(ProviderWalletTransaction::STATUS_AVAILABLE, $credit->status, 'earning must be status=available');
        $this->assertSame(ProviderWalletTransaction::DIRECTION_CREDIT, $credit->direction, 'earning must be direction=credit');
    }
}

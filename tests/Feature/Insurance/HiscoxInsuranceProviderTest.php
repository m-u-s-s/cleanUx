<?php

namespace Tests\Feature\Insurance;

use App\Services\Insurance\ClaimFilingRequest;
use App\Services\Insurance\InsurancePurchaseRequest;
use App\Services\Insurance\InsuranceWebhookUpdate;
use App\Services\Insurance\Providers\HiscoxInsuranceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HiscoxInsuranceProviderTest extends TestCase
{
    private HiscoxInsuranceProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new HiscoxInsuranceProvider;
    }

    private function configureProvider(): void
    {
        Config::set('insurance.providers.hiscox.base_url', 'https://api.hiscox.test');
        Config::set('insurance.providers.hiscox.api_key', 'secret-key');
        Config::set('insurance.providers.hiscox.webhook_secret', 'whsec');
    }

    private function clearProvider(): void
    {
        Config::set('insurance.providers.hiscox.base_url', '');
        Config::set('insurance.providers.hiscox.api_key', '');
    }

    private function purchaseRequest(): InsurancePurchaseRequest
    {
        return new InsurancePurchaseRequest(
            planCode: 'basic',
            bookingId: 42,
            premiumCents: 1500,
            coverageCents: 100000,
            currency: 'EUR',
            effectiveFrom: new \DateTimeImmutable('2026-06-01'),
            effectiveUntil: new \DateTimeImmutable('2026-06-30'),
            idempotencyKey: 'idem-123',
        );
    }

    private function claimRequest(): ClaimFilingRequest
    {
        return new ClaimFilingRequest(
            policyExternalId: 'pol_ext_1',
            incidentType: 'damage',
            incidentDescription: 'Wall scratched during cleaning.',
            incidentDate: new \DateTimeImmutable('2026-06-05'),
            amountClaimedCents: 40000,
            currency: 'EUR',
            evidence: ['photo1.jpg'],
            idempotencyKey: 'claim-idem-1',
        );
    }

    public function test_name_returns_hiscox(): void
    {
        $this->assertSame('hiscox', $this->provider->name());
    }

    public function test_purchase_returns_failure_when_config_missing(): void
    {
        $this->clearProvider();

        $result = $this->provider->purchase($this->purchaseRequest());

        $this->assertFalse($result->accepted);
        $this->assertSame('hiscox_config', $result->failureCode);
        $this->assertSame('Hiscox config missing', $result->failureReason);
    }

    public function test_purchase_accepted_on_successful_response(): void
    {
        $this->configureProvider();
        Http::fake([
            'https://api.hiscox.test/policies' => Http::response([
                'id' => 'pol_999',
                'policy_number' => 'HX-2026-999',
            ], 201),
        ]);

        $result = $this->provider->purchase($this->purchaseRequest());

        $this->assertTrue($result->accepted);
        $this->assertSame('pol_999', $result->externalId);
        $this->assertSame('HX-2026-999', $result->policyNumber);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer secret-key')
                && $request['plan_code'] === 'basic'
                && $request['booking_reference'] === 42
                && $request['effective_from'] === '2026-06-01'
                && $request['idempotency_key'] === 'idem-123';
        });
    }

    public function test_purchase_failed_on_error_response(): void
    {
        $this->configureProvider();
        Http::fake([
            'https://api.hiscox.test/policies' => Http::response([
                'message' => 'Plan not allowed',
                'code' => 'plan_rejected',
            ], 422),
        ]);

        $result = $this->provider->purchase($this->purchaseRequest());

        $this->assertFalse($result->accepted);
        $this->assertSame('Plan not allowed', $result->failureReason);
        $this->assertSame('plan_rejected', $result->failureCode);
    }

    public function test_purchase_failed_uses_defaults_when_error_body_empty(): void
    {
        $this->configureProvider();
        Http::fake([
            'https://api.hiscox.test/policies' => Http::response(null, 500),
        ]);

        $result = $this->provider->purchase($this->purchaseRequest());

        $this->assertFalse($result->accepted);
        $this->assertSame('Hiscox error', $result->failureReason);
        $this->assertSame('hiscox_unknown', $result->failureCode);
    }

    public function test_cancel_policy_returns_failure_when_config_missing(): void
    {
        $this->clearProvider();

        $result = $this->provider->cancelPolicy('pol_1');

        $this->assertFalse($result->accepted);
        $this->assertSame('Hiscox config missing', $result->failureReason);
    }

    public function test_cancel_policy_ok_on_success(): void
    {
        $this->configureProvider();
        Http::fake([
            'https://api.hiscox.test/policies/pol_1' => Http::response(['cancelled' => true], 200),
        ]);

        $result = $this->provider->cancelPolicy('pol_1');

        $this->assertTrue($result->accepted);
        $this->assertSame(['cancelled' => true], $result->raw);
    }

    public function test_cancel_policy_failed_on_error(): void
    {
        $this->configureProvider();
        Http::fake([
            'https://api.hiscox.test/policies/pol_2' => Http::response(['message' => 'Not found'], 404),
        ]);

        $result = $this->provider->cancelPolicy('pol_2');

        $this->assertFalse($result->accepted);
        $this->assertSame('Not found', $result->failureReason);
    }

    public function test_file_claim_returns_failure_when_config_missing(): void
    {
        $this->clearProvider();

        $result = $this->provider->fileClaim($this->claimRequest());

        $this->assertFalse($result->accepted);
        $this->assertSame('Hiscox config missing', $result->failureReason);
    }

    public function test_file_claim_accepted_on_success(): void
    {
        $this->configureProvider();
        Http::fake([
            'https://api.hiscox.test/claims' => Http::response([
                'id' => 'clm_55',
                'status' => 'under_review',
            ], 201),
        ]);

        $result = $this->provider->fileClaim($this->claimRequest());

        $this->assertTrue($result->accepted);
        $this->assertSame('clm_55', $result->externalClaimId);
        $this->assertSame('under_review', $result->status);

        Http::assertSent(function ($request) {
            return $request['policy_id'] === 'pol_ext_1'
                && $request['incident_type'] === 'damage'
                && $request['incident_date'] === '2026-06-05'
                && $request['amount_cents'] === 40000;
        });
    }

    public function test_file_claim_defaults_status_to_filed_when_missing(): void
    {
        $this->configureProvider();
        Http::fake([
            'https://api.hiscox.test/claims' => Http::response(['id' => 'clm_77'], 200),
        ]);

        $result = $this->provider->fileClaim($this->claimRequest());

        $this->assertTrue($result->accepted);
        $this->assertSame('filed', $result->status);
    }

    public function test_file_claim_failed_on_error(): void
    {
        $this->configureProvider();
        Http::fake([
            'https://api.hiscox.test/claims' => Http::response(['message' => 'Bad claim'], 400),
        ]);

        $result = $this->provider->fileClaim($this->claimRequest());

        $this->assertFalse($result->accepted);
        $this->assertSame('Bad claim', $result->failureReason);
    }

    public function test_file_claim_failed_default_message(): void
    {
        $this->configureProvider();
        Http::fake([
            'https://api.hiscox.test/claims' => Http::response(null, 503),
        ]);

        $result = $this->provider->fileClaim($this->claimRequest());

        $this->assertFalse($result->accepted);
        $this->assertSame('Hiscox claim failed', $result->failureReason);
    }

    public function test_verify_webhook_passes_with_valid_signature(): void
    {
        Config::set('insurance.providers.hiscox.webhook_secret', 'whsec');
        $payload = json_encode(['event_type' => 'policy.activated', 'policy_id' => 'p1']);
        $signature = hash_hmac('sha256', $payload, 'whsec');

        $decoded = $this->provider->verifyWebhook($payload, ['hiscox-signature' => [$signature]]);

        $this->assertSame('policy.activated', $decoded['event_type']);
    }

    public function test_verify_webhook_throws_on_invalid_signature(): void
    {
        Config::set('insurance.providers.hiscox.webhook_secret', 'whsec');
        $payload = json_encode(['event_type' => 'policy.activated']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid Hiscox webhook signature');

        $this->provider->verifyWebhook($payload, ['hiscox-signature' => ['wrong-signature']]);
    }

    public function test_verify_webhook_skips_check_when_no_secret(): void
    {
        Config::set('insurance.providers.hiscox.webhook_secret', '');
        $payload = json_encode(['event_type' => 'claim.paid']);

        $decoded = $this->provider->verifyWebhook($payload, []);

        $this->assertSame('claim.paid', $decoded['event_type']);
    }

    public function test_verify_webhook_throws_on_non_json_payload(): void
    {
        Config::set('insurance.providers.hiscox.webhook_secret', '');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Hiscox webhook payload not JSON');

        $this->provider->verifyWebhook('not-json-at-all', []);
    }

    public function test_map_webhook_event_returns_null_without_event_type(): void
    {
        $this->assertNull($this->provider->mapWebhookEvent(['foo' => 'bar']));
    }

    public function test_map_webhook_event_returns_null_for_unknown_prefix(): void
    {
        $this->assertNull($this->provider->mapWebhookEvent(['event_type' => 'invoice.created']));
    }

    public function test_map_webhook_event_maps_policy_event(): void
    {
        $update = $this->provider->mapWebhookEvent([
            'event_type' => 'policy.cancelled',
            'policy_id' => 'pol_abc',
            'status' => 'cancelled',
        ]);

        $this->assertInstanceOf(InsuranceWebhookUpdate::class, $update);
        $this->assertSame(InsuranceWebhookUpdate::TARGET_POLICY, $update->target);
        $this->assertSame('pol_abc', $update->externalId);
        $this->assertSame('cancelled', $update->newStatus);
    }

    public function test_map_webhook_event_policy_falls_back_to_event_type_status(): void
    {
        $update = $this->provider->mapWebhookEvent([
            'event_type' => 'policy.activated',
            'policy_id' => 'pol_xyz',
        ]);

        $this->assertInstanceOf(InsuranceWebhookUpdate::class, $update);
        $this->assertSame('policy.activated', $update->newStatus);
    }

    public function test_map_webhook_event_maps_claim_event(): void
    {
        $update = $this->provider->mapWebhookEvent([
            'event_type' => 'claim.paid',
            'claim_id' => 'clm_abc',
            'status' => 'paid',
            'amount_settled_cents' => '35000',
            'reason' => 'Settled in full',
        ]);

        $this->assertInstanceOf(InsuranceWebhookUpdate::class, $update);
        $this->assertSame(InsuranceWebhookUpdate::TARGET_CLAIM, $update->target);
        $this->assertSame('clm_abc', $update->externalId);
        $this->assertSame('paid', $update->newStatus);
        $this->assertSame(35000, $update->amountSettledCents);
        $this->assertSame('Settled in full', $update->reason);
    }

    public function test_map_webhook_event_claim_without_settled_amount(): void
    {
        $update = $this->provider->mapWebhookEvent([
            'event_type' => 'claim.under_review',
            'claim_id' => 'clm_def',
        ]);

        $this->assertInstanceOf(InsuranceWebhookUpdate::class, $update);
        $this->assertSame('claim.under_review', $update->newStatus);
        $this->assertNull($update->amountSettledCents);
        $this->assertNull($update->reason);
    }
}

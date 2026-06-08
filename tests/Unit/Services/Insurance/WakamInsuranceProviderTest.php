<?php

namespace Tests\Unit\Services\Insurance;

use App\Services\Insurance\ClaimFilingRequest;
use App\Services\Insurance\InsurancePurchaseRequest;
use App\Services\Insurance\InsuranceWebhookUpdate;
use App\Services\Insurance\Providers\WakamInsuranceProvider;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit coverage for the Wakam insurance provider skeleton: purchase, cancel,
 * claim filing, webhook verification + event mapping, including the config-missing
 * guards and HTTP success/failure branches.
 */
class WakamInsuranceProviderTest extends TestCase
{
    private WakamInsuranceProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new WakamInsuranceProvider;
        Config::set('insurance.providers.wakam.base_url', 'https://wakam.test/api');
        Config::set('insurance.providers.wakam.api_key', 'secret-key');
        Config::set('insurance.providers.wakam.webhook_secret', 'whsec');
    }

    public function test_name_is_wakam(): void
    {
        $this->assertSame('wakam', $this->provider->name());
    }

    private function purchaseRequest(): InsurancePurchaseRequest
    {
        return new InsurancePurchaseRequest(
            planCode: 'PRO',
            bookingId: 42,
            premiumCents: 1500,
            coverageCents: 500000,
            currency: 'EUR',
            idempotencyKey: 'idem-1',
        );
    }

    private function claimRequest(): ClaimFilingRequest
    {
        return new ClaimFilingRequest(
            policyExternalId: 'ctr_9',
            incidentType: 'water_damage',
            incidentDescription: 'Burst pipe',
            incidentDate: Carbon::parse('2026-01-15'),
            amountClaimedCents: 25000,
            currency: 'EUR',
            evidence: ['photo1.jpg'],
            idempotencyKey: 'claim-idem-1',
        );
    }

    public function test_purchase_fails_when_config_missing(): void
    {
        Config::set('insurance.providers.wakam.base_url', '');
        Config::set('insurance.providers.wakam.api_key', '');

        $result = $this->provider->purchase($this->purchaseRequest());

        $this->assertFalse($result->accepted);
        $this->assertSame('wakam_config', $result->failureCode);
        $this->assertSame('Wakam config missing', $result->failureReason);
    }

    public function test_purchase_accepted_on_successful_http(): void
    {
        Http::fake([
            'wakam.test/api/contracts' => Http::response([
                'contract_id' => 'ctr_123',
                'contract_number' => 'WK-001',
            ], 201),
        ]);

        $result = $this->provider->purchase($this->purchaseRequest());

        $this->assertTrue($result->accepted);
        $this->assertSame('ctr_123', $result->externalId);
        $this->assertSame('WK-001', $result->policyNumber);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer secret-key')
                && $request['product_code'] === 'PRO'
                && $request['premium'] == 15
                && $request['coverage'] == 5000
                && $request['idempotency_key'] === 'idem-1';
        });
    }

    public function test_purchase_failed_on_http_error(): void
    {
        Http::fake([
            'wakam.test/api/contracts' => Http::response(['error' => 'denied'], 422),
        ]);

        $result = $this->provider->purchase($this->purchaseRequest());

        $this->assertFalse($result->accepted);
        $this->assertSame('denied', $result->failureReason);
        $this->assertSame('wakam_http_422', $result->failureCode);
    }

    public function test_cancel_fails_when_config_missing(): void
    {
        Config::set('insurance.providers.wakam.api_key', '');

        $result = $this->provider->cancelPolicy('ctr_1');

        $this->assertFalse($result->accepted);
        $this->assertSame('Wakam config missing', $result->failureReason);
    }

    public function test_cancel_ok_on_success(): void
    {
        Http::fake([
            'wakam.test/api/contracts/ctr_1/cancel' => Http::response(['status' => 'cancelled'], 200),
        ]);

        $result = $this->provider->cancelPolicy('ctr_1');

        $this->assertTrue($result->accepted);
        $this->assertSame('cancelled', $result->raw['status']);
    }

    public function test_cancel_failed_on_http_error(): void
    {
        Http::fake([
            'wakam.test/api/contracts/ctr_1/cancel' => Http::response(['error' => 'nope'], 500),
        ]);

        $result = $this->provider->cancelPolicy('ctr_1');

        $this->assertFalse($result->accepted);
        $this->assertSame('nope', $result->failureReason);
    }

    public function test_file_claim_fails_when_config_missing(): void
    {
        Config::set('insurance.providers.wakam.base_url', '');

        $result = $this->provider->fileClaim($this->claimRequest());

        $this->assertFalse($result->accepted);
        $this->assertSame('Wakam config missing', $result->failureReason);
    }

    public function test_file_claim_accepted_on_success(): void
    {
        Http::fake([
            'wakam.test/api/sinistres' => Http::response([
                'sinistre_id' => 'sin_77',
                'statut' => 'en_cours',
            ], 201),
        ]);

        $result = $this->provider->fileClaim($this->claimRequest());

        $this->assertTrue($result->accepted);
        $this->assertSame('sin_77', $result->externalClaimId);
        $this->assertSame('en_cours', $result->status);

        Http::assertSent(function ($request) {
            return $request['contract_id'] === 'ctr_9'
                && $request['type_sinistre'] === 'water_damage'
                && $request['date_sinistre'] === '2026-01-15'
                && $request['montant'] == 250;
        });
    }

    public function test_file_claim_defaults_status_filed_when_absent(): void
    {
        Http::fake([
            'wakam.test/api/sinistres' => Http::response(['sinistre_id' => 'sin_1'], 200),
        ]);

        $result = $this->provider->fileClaim($this->claimRequest());

        $this->assertTrue($result->accepted);
        $this->assertSame('filed', $result->status);
    }

    public function test_file_claim_failed_on_http_error(): void
    {
        Http::fake([
            'wakam.test/api/sinistres' => Http::response(['error' => 'rejected'], 400),
        ]);

        $result = $this->provider->fileClaim($this->claimRequest());

        $this->assertFalse($result->accepted);
        $this->assertSame('rejected', $result->failureReason);
    }

    public function test_verify_webhook_returns_decoded_with_valid_signature(): void
    {
        $payload = json_encode(['type' => 'contract.activated', 'contract_id' => 'ctr_1']);
        $signature = hash_hmac('sha256', $payload, 'whsec');

        $decoded = $this->provider->verifyWebhook($payload, ['x-wakam-signature' => [$signature]]);

        $this->assertSame('contract.activated', $decoded['type']);
    }

    public function test_verify_webhook_throws_on_invalid_signature(): void
    {
        $payload = json_encode(['type' => 'contract.activated']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid Wakam webhook signature');

        $this->provider->verifyWebhook($payload, ['x-wakam-signature' => ['deadbeef']]);
    }

    public function test_verify_webhook_skips_signature_when_no_secret(): void
    {
        Config::set('insurance.providers.wakam.webhook_secret', '');
        $payload = json_encode(['type' => 'sinistre.updated']);

        $decoded = $this->provider->verifyWebhook($payload, []);

        $this->assertSame('sinistre.updated', $decoded['type']);
    }

    public function test_verify_webhook_throws_on_non_json(): void
    {
        Config::set('insurance.providers.wakam.webhook_secret', '');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Wakam webhook payload not JSON');

        $this->provider->verifyWebhook('not-json', []);
    }

    public function test_map_webhook_event_returns_null_without_type(): void
    {
        $this->assertNull($this->provider->mapWebhookEvent(['foo' => 'bar']));
    }

    public function test_map_webhook_event_returns_null_for_unknown_type(): void
    {
        $this->assertNull($this->provider->mapWebhookEvent(['type' => 'payment.received']));
    }

    public function test_map_webhook_event_maps_contract_to_policy(): void
    {
        $update = $this->provider->mapWebhookEvent([
            'type' => 'contract.cancelled',
            'contract_id' => 'ctr_55',
            'statut' => 'cancelled',
        ]);

        $this->assertInstanceOf(InsuranceWebhookUpdate::class, $update);
        $this->assertSame(InsuranceWebhookUpdate::TARGET_POLICY, $update->target);
        $this->assertSame('ctr_55', $update->externalId);
        $this->assertSame('cancelled', $update->newStatus);
    }

    public function test_map_webhook_event_maps_sinistre_to_claim_with_settlement(): void
    {
        $update = $this->provider->mapWebhookEvent([
            'type' => 'sinistre.settled',
            'sinistre_id' => 'sin_9',
            'statut' => 'indemnise',
            'montant_indemnise' => 123.45,
            'motif' => 'covered',
        ]);

        $this->assertInstanceOf(InsuranceWebhookUpdate::class, $update);
        $this->assertSame(InsuranceWebhookUpdate::TARGET_CLAIM, $update->target);
        $this->assertSame('sin_9', $update->externalId);
        $this->assertSame('indemnise', $update->newStatus);
        $this->assertSame(12345, $update->amountSettledCents);
        $this->assertSame('covered', $update->reason);
    }

    public function test_map_webhook_event_sinistre_without_settlement_amount(): void
    {
        $update = $this->provider->mapWebhookEvent([
            'type' => 'sinistre.opened',
            'sinistre_id' => 'sin_2',
        ]);

        $this->assertInstanceOf(InsuranceWebhookUpdate::class, $update);
        $this->assertSame('sinistre.opened', $update->newStatus);
        $this->assertNull($update->amountSettledCents);
        $this->assertNull($update->reason);
    }
}

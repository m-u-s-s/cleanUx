<?php

namespace App\Services\Insurance\Providers;

use App\Models\BookingInsurance;
use App\Models\InsuranceClaim;
use App\Services\Insurance\ClaimFilingRequest;
use App\Services\Insurance\ClaimFilingResult;
use App\Services\Insurance\InsuranceCancelResult;
use App\Services\Insurance\InsuranceProviderInterface;
use App\Services\Insurance\InsurancePurchaseRequest;
use App\Services\Insurance\InsurancePurchaseResult;
use App\Services\Insurance\InsuranceWebhookUpdate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Mock provider — pour dev/tests.
 *
 * Comportements spéciaux:
 *   - plan_code contenant "fail" → purchase failed
 *   - incident_type === "fraud_simulation" → claim immediately rejected
 */
class InsuranceMockProvider implements InsuranceProviderInterface
{
    public function name(): string
    {
        return 'mock';
    }

    public function purchase(InsurancePurchaseRequest $request): InsurancePurchaseResult
    {
        Log::info('InsuranceMockProvider::purchase', [
            'plan_code' => $request->planCode,
            'booking_id' => $request->bookingId,
            'premium_cents' => $request->premiumCents,
        ]);

        if (str_contains(strtolower($request->planCode), 'fail')) {
            return InsurancePurchaseResult::failed('Mock auto-fail', 'mock_fail');
        }

        return InsurancePurchaseResult::accepted(
            'mock_pol_' . Str::lower(Str::random(12)),
            'POL-' . strtoupper(Str::random(8)),
            ['simulated' => true],
        );
    }

    public function cancelPolicy(string $externalId): InsuranceCancelResult
    {
        Log::info('InsuranceMockProvider::cancelPolicy', ['external_id' => $externalId]);
        return InsuranceCancelResult::ok(['simulated' => true]);
    }

    public function fileClaim(ClaimFilingRequest $request): ClaimFilingResult
    {
        Log::info('InsuranceMockProvider::fileClaim', [
            'policy' => $request->policyExternalId,
            'incident_type' => $request->incidentType,
            'amount' => $request->amountClaimedCents,
        ]);

        if ($request->incidentType === 'fraud_simulation') {
            return ClaimFilingResult::accepted(
                'mock_clm_' . Str::lower(Str::random(12)),
                InsuranceClaim::STATUS_REJECTED,
                ['simulated' => true, 'auto_rejected' => true],
            );
        }

        return ClaimFilingResult::accepted(
            'mock_clm_' . Str::lower(Str::random(12)),
            InsuranceClaim::STATUS_FILED,
            ['simulated' => true],
        );
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : ['raw' => $payload];
    }

    public function mapWebhookEvent(array $payload): ?InsuranceWebhookUpdate
    {
        $target = $payload['target'] ?? null;
        $externalId = $payload['external_id'] ?? null;
        $newStatus = $payload['status'] ?? null;

        if (! $target || ! $externalId || ! $newStatus) {
            return null;
        }

        return new InsuranceWebhookUpdate(
            target: $target,
            externalId: (string) $externalId,
            newStatus: (string) $newStatus,
            amountSettledCents: isset($payload['amount_settled_cents']) ? (int) $payload['amount_settled_cents'] : null,
            reason: $payload['reason'] ?? null,
            raw: $payload,
        );
    }
}

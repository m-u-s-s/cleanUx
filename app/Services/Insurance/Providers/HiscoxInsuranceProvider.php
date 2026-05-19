<?php

namespace App\Services\Insurance\Providers;

use App\Services\Insurance\ClaimFilingRequest;
use App\Services\Insurance\ClaimFilingResult;
use App\Services\Insurance\InsuranceCancelResult;
use App\Services\Insurance\InsuranceProviderInterface;
use App\Services\Insurance\InsurancePurchaseRequest;
use App\Services\Insurance\InsurancePurchaseResult;
use App\Services\Insurance\InsuranceWebhookUpdate;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Hiscox skeleton — méthodes structurées pour câblage prod ultérieur.
 * Toute l'auth + signature est skeleton-only, à compléter selon les specs Hiscox.
 */
class HiscoxInsuranceProvider implements InsuranceProviderInterface
{
    public function name(): string
    {
        return 'hiscox';
    }

    public function purchase(InsurancePurchaseRequest $request): InsurancePurchaseResult
    {
        $base = (string) Config::get('insurance.providers.hiscox.base_url', '');
        $apiKey = (string) Config::get('insurance.providers.hiscox.api_key', '');
        if (! $base || ! $apiKey) {
            return InsurancePurchaseResult::failed('Hiscox config missing', 'hiscox_config');
        }

        $response = Http::withToken($apiKey)
            ->timeout(15)
            ->post("{$base}/policies", [
                'plan_code' => $request->planCode,
                'booking_reference' => $request->bookingId,
                'premium_cents' => $request->premiumCents,
                'coverage_cents' => $request->coverageCents,
                'currency' => $request->currency,
                'effective_from' => $request->effectiveFrom?->format('Y-m-d'),
                'effective_until' => $request->effectiveUntil?->format('Y-m-d'),
                'idempotency_key' => $request->idempotencyKey,
            ]);

        if ($response->successful()) {
            return InsurancePurchaseResult::accepted(
                (string) $response->json('id'),
                $response->json('policy_number'),
                $response->json() ?? [],
            );
        }

        return InsurancePurchaseResult::failed(
            $response->json('message') ?? 'Hiscox error',
            $response->json('code') ?? 'hiscox_unknown',
            $response->json() ?? [],
        );
    }

    public function cancelPolicy(string $externalId): InsuranceCancelResult
    {
        $base = (string) Config::get('insurance.providers.hiscox.base_url', '');
        $apiKey = (string) Config::get('insurance.providers.hiscox.api_key', '');
        if (! $base || ! $apiKey) {
            return InsuranceCancelResult::failed('Hiscox config missing');
        }

        $response = Http::withToken($apiKey)
            ->delete("{$base}/policies/{$externalId}");

        return $response->successful()
            ? InsuranceCancelResult::ok($response->json() ?? [])
            : InsuranceCancelResult::failed($response->json('message') ?? 'Cancel failed', $response->json() ?? []);
    }

    public function fileClaim(ClaimFilingRequest $request): ClaimFilingResult
    {
        $base = (string) Config::get('insurance.providers.hiscox.base_url', '');
        $apiKey = (string) Config::get('insurance.providers.hiscox.api_key', '');
        if (! $base || ! $apiKey) {
            return ClaimFilingResult::failed('Hiscox config missing');
        }

        $response = Http::withToken($apiKey)
            ->timeout(20)
            ->post("{$base}/claims", [
                'policy_id' => $request->policyExternalId,
                'incident_type' => $request->incidentType,
                'description' => $request->incidentDescription,
                'incident_date' => $request->incidentDate->format('Y-m-d'),
                'amount_cents' => $request->amountClaimedCents,
                'currency' => $request->currency,
                'evidence' => $request->evidence,
                'idempotency_key' => $request->idempotencyKey,
            ]);

        if ($response->successful()) {
            return ClaimFilingResult::accepted(
                (string) $response->json('id'),
                $response->json('status') ?? 'filed',
                $response->json() ?? [],
            );
        }

        return ClaimFilingResult::failed(
            $response->json('message') ?? 'Hiscox claim failed',
            $response->json() ?? [],
        );
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $secret = (string) Config::get('insurance.providers.hiscox.webhook_secret', '');
        $signature = $headers['hiscox-signature'][0] ?? '';

        if ($secret && $signature) {
            $expected = hash_hmac('sha256', $payload, $secret);
            if (! hash_equals($expected, $signature)) {
                throw new \RuntimeException('Invalid Hiscox webhook signature');
            }
        }

        $decoded = json_decode($payload, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('Hiscox webhook payload not JSON');
        }
        return $decoded;
    }

    public function mapWebhookEvent(array $payload): ?InsuranceWebhookUpdate
    {
        $eventType = $payload['event_type'] ?? null;
        if (! $eventType) {
            return null;
        }

        if (str_starts_with($eventType, 'policy.')) {
            return new InsuranceWebhookUpdate(
                target: InsuranceWebhookUpdate::TARGET_POLICY,
                externalId: (string) ($payload['policy_id'] ?? ''),
                newStatus: (string) ($payload['status'] ?? $eventType),
                raw: $payload,
            );
        }

        if (str_starts_with($eventType, 'claim.')) {
            return new InsuranceWebhookUpdate(
                target: InsuranceWebhookUpdate::TARGET_CLAIM,
                externalId: (string) ($payload['claim_id'] ?? ''),
                newStatus: (string) ($payload['status'] ?? $eventType),
                amountSettledCents: isset($payload['amount_settled_cents']) ? (int) $payload['amount_settled_cents'] : null,
                reason: $payload['reason'] ?? null,
                raw: $payload,
            );
        }

        return null;
    }
}

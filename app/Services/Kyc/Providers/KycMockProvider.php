<?php

namespace App\Services\Kyc\Providers;

use App\Models\KycCheck;
use App\Models\KycVerification;
use App\Services\Kyc\KycProviderInterface;
use App\Services\Kyc\KycStartRequest;
use App\Services\Kyc\KycStartResult;
use App\Services\Kyc\KycStatusResult;
use Illuminate\Support\Str;

/**
 * Provider KYC simulé pour développement et tests.
 *
 * Comportement par défaut :
 *   - startVerification → renvoie un applicant_id mock
 *   - fetchStatus → "clear" + score 0.9 + tous les checks "clear"
 *   - Si le user.email contient "reject" → status "rejected"
 *   - Si le user.email contient "review" → status "consider"
 *
 * Permet de tester tout le pipeline sans dépendance externe.
 */
class KycMockProvider implements KycProviderInterface
{
    public function name(): string
    {
        return 'mock';
    }

    public function startVerification(KycStartRequest $request): KycStartResult
    {
        return new KycStartResult(
            externalApplicantId: 'mock_app_' . Str::lower(Str::random(12)),
            externalCheckId: 'mock_chk_' . Str::lower(Str::random(12)),
            hostedFlowUrl: null,
            raw: [
                'simulated' => true,
                'user_id' => $request->user->id,
                'country_code' => $request->countryCode,
                'requested_checks' => $request->requestedChecks,
            ],
        );
    }

    public function fetchStatus(KycVerification $verification): KycStatusResult
    {
        $email = strtolower((string) $verification->user?->email);

        $decision = match (true) {
            str_contains($email, 'reject') => 'rejected',
            str_contains($email, 'review') => 'manual_review',
            default => 'approved',
        };

        $status = match ($decision) {
            'rejected' => KycVerification::STATUS_REJECTED,
            'manual_review' => KycVerification::STATUS_CONSIDER,
            default => KycVerification::STATUS_CLEAR,
        };

        $checkResult = match ($decision) {
            'rejected' => KycCheck::RESULT_REJECTED,
            'manual_review' => KycCheck::RESULT_CONSIDER,
            default => KycCheck::RESULT_CLEAR,
        };

        $checks = [];
        foreach ((array) $verification->requested_checks as $type) {
            $checks[] = [
                'type' => $type,
                'result' => $checkResult,
                'confidence' => $checkResult === KycCheck::RESULT_CLEAR ? 0.92 : 0.55,
                'external_id' => 'mock_chk_' . Str::lower(Str::random(8)),
            ];
        }

        return new KycStatusResult(
            status: $status,
            decision: $decision,
            score: $decision === 'rejected' ? 0.2 : ($decision === 'manual_review' ? 0.55 : 0.92),
            checks: $checks,
            rejectionReason: $decision === 'rejected' ? 'Mock provider auto-reject (email contains "reject")' : null,
            raw: ['simulated' => true],
        );
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        // Mock : pas de signature requise
        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : ['raw' => $payload];
    }

    public function mapWebhookEvent(array $payload): ?KycStatusResult
    {
        $decision = (string) ($payload['decision'] ?? 'approved');
        $status = (string) ($payload['status'] ?? KycVerification::STATUS_CLEAR);
        $score = isset($payload['score']) ? (float) $payload['score'] : 0.9;

        return new KycStatusResult(
            status: $status,
            decision: $decision,
            score: $score,
            checks: $payload['checks'] ?? [],
            rejectionReason: $payload['rejection_reason'] ?? null,
            raw: $payload,
        );
    }
}

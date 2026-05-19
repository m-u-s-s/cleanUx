<?php

namespace App\Services\Kyc\Providers;

use App\Models\KycCheck;
use App\Models\KycVerification;
use App\Services\Kyc\KycProviderInterface;
use App\Services\Kyc\KycStartRequest;
use App\Services\Kyc\KycStartResult;
use App\Services\Kyc\KycStatusResult;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Adapter Onfido (https://documentation.onfido.com/).
 *
 * Skeleton — appels HTTP de base. À enrichir selon les besoins :
 *   - Workflow SDK token pour iframe / mobile SDK
 *   - Multi-document support
 *   - Custom check configurations
 *
 * Configuration via `config/kyc.php > providers.onfido` :
 *   - api_token (REST API token)
 *   - region (eu|us|ca)
 *   - webhook_token (token HMAC pour vérifier signature webhooks)
 */
class OnfidoProvider implements KycProviderInterface
{
    public function name(): string
    {
        return 'onfido';
    }

    public function startVerification(KycStartRequest $request): KycStartResult
    {
        $applicant = $this->client()->post('/applicants', [
            'first_name' => $this->firstName($request->user),
            'last_name' => $this->lastName($request->user),
            'email' => $request->user->email,
            'location' => ['country_of_residence' => strtoupper($request->countryCode)],
        ]);

        if ($applicant->failed()) {
            throw new RuntimeException('Onfido create applicant failed: ' . $applicant->body());
        }

        $applicantId = (string) $applicant->json('id');

        return new KycStartResult(
            externalApplicantId: $applicantId,
            externalCheckId: null,
            hostedFlowUrl: null,
            raw: $applicant->json() ?? [],
        );
    }

    public function fetchStatus(KycVerification $verification): KycStatusResult
    {
        $checkId = $verification->external_check_id;
        if (! $checkId) {
            return new KycStatusResult(
                status: KycVerification::STATUS_AWAITING_DOCS,
                decision: KycVerification::DECISION_PENDING,
            );
        }

        $check = $this->client()->get("/checks/{$checkId}");
        if ($check->failed()) {
            throw new RuntimeException('Onfido fetch check failed: ' . $check->body());
        }

        return $this->mapCheckResponse($check->json() ?? []);
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $token = (string) config('kyc.providers.onfido.webhook_token', '');
        if ($token === '') {
            throw new RuntimeException('Onfido webhook token missing (kyc.providers.onfido.webhook_token).');
        }

        $signature = $headers['X-SHA2-Signature'][0] ?? $headers['x-sha2-signature'][0] ?? null;
        if (! $signature) {
            throw new RuntimeException('Missing X-SHA2-Signature header.');
        }

        $computed = hash_hmac('sha256', $payload, $token);
        if (! hash_equals($computed, $signature)) {
            throw new RuntimeException('Invalid Onfido webhook signature.');
        }

        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function mapWebhookEvent(array $payload): ?KycStatusResult
    {
        $resource = $payload['payload']['resource_type'] ?? null;
        if ($resource !== 'check') {
            return null;
        }

        $object = $payload['payload']['object'] ?? [];
        if (empty($object)) {
            return null;
        }

        return $this->mapCheckResponse($object);
    }

    protected function client()
    {
        $config = (array) config('kyc.providers.onfido', []);
        $token = (string) ($config['api_token'] ?? '');
        $region = strtolower((string) ($config['region'] ?? 'eu'));

        $baseUrl = match ($region) {
            'us' => 'https://api.us.onfido.com/v3.6',
            'ca' => 'https://api.ca.onfido.com/v3.6',
            default => 'https://api.eu.onfido.com/v3.6',
        };

        if ($token === '') {
            throw new RuntimeException('Onfido API token missing (kyc.providers.onfido.api_token).');
        }

        return Http::withToken($token, 'Token')
            ->baseUrl($baseUrl)
            ->acceptJson()
            ->asJson();
    }

    protected function mapCheckResponse(array $body): KycStatusResult
    {
        // Onfido check.result : 'clear' | 'consider' | null (pending)
        // Onfido check.status : 'awaiting_data' | 'in_progress' | 'complete' | 'withdrawn'
        $result = $body['result'] ?? null;
        $status = $body['status'] ?? null;

        $mappedStatus = match (true) {
            $result === 'clear' => KycVerification::STATUS_CLEAR,
            $result === 'consider' => KycVerification::STATUS_CONSIDER,
            $status === 'awaiting_data' => KycVerification::STATUS_AWAITING_DOCS,
            $status === 'in_progress' => KycVerification::STATUS_IN_REVIEW,
            $status === 'withdrawn' => KycVerification::STATUS_CANCELLED,
            default => KycVerification::STATUS_IN_REVIEW,
        };

        $decision = match ($result) {
            'clear' => KycVerification::DECISION_APPROVED,
            'consider' => KycVerification::DECISION_MANUAL_REVIEW,
            default => KycVerification::DECISION_PENDING,
        };

        $checks = [];
        foreach ((array) ($body['report_ids'] ?? []) as $reportId) {
            $checks[] = [
                'type' => KycCheck::TYPE_DOCUMENT,
                'result' => $result === 'clear' ? KycCheck::RESULT_CLEAR : KycCheck::RESULT_CONSIDER,
                'external_id' => (string) $reportId,
            ];
        }

        return new KycStatusResult(
            status: $mappedStatus,
            decision: $decision,
            score: null,
            checks: $checks,
            rejectionReason: $result === 'consider' ? ($body['sub_result'] ?? 'Manual review required by Onfido') : null,
            raw: $body,
        );
    }

    protected function firstName($user): string
    {
        $parts = preg_split('/\s+/', (string) $user->name, 2);
        return $parts[0] ?? 'Unknown';
    }

    protected function lastName($user): string
    {
        $parts = preg_split('/\s+/', (string) $user->name, 2);
        return $parts[1] ?? '-';
    }
}

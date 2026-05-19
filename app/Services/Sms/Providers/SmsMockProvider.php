<?php

namespace App\Services\Sms\Providers;

use App\Services\Sms\SmsProviderInterface;
use App\Services\Sms\SmsSendRequest;
use App\Services\Sms\SmsSendResult;
use App\Services\Sms\SmsStatusUpdate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Provider SMS mock — log au lieu d'envoyer réellement.
 *
 * Comportement spécial:
 *   - to_phone contenant "fail" → renvoie failed
 *   - to_phone contenant "undeliver" → undelivered
 *   - sinon → accepté + status=sent
 */
class SmsMockProvider implements SmsProviderInterface
{
    public function name(): string
    {
        return 'mock';
    }

    public function send(SmsSendRequest $request): SmsSendResult
    {
        Log::info('SmsMockProvider::send', [
            'to' => $request->toPhone,
            'body' => $request->body,
            'idempotency_key' => $request->idempotencyKey,
        ]);

        if (str_contains(strtolower($request->toPhone), 'fail')) {
            return SmsSendResult::failed('Mock provider auto-fail (phone contains "fail")', 'mock_fail');
        }

        if (str_contains(strtolower($request->toPhone), 'undeliver')) {
            return SmsSendResult::accepted(
                'mock_sms_' . Str::lower(Str::random(12)),
                'sent',
                ['simulated' => true],
            );
        }

        return SmsSendResult::accepted(
            'mock_sms_' . Str::lower(Str::random(12)),
            'sent',
            ['simulated' => true, 'len' => strlen($request->body)],
        );
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : ['raw' => $payload];
    }

    public function mapWebhookEvent(array $payload): ?SmsStatusUpdate
    {
        $externalId = $payload['external_id'] ?? $payload['id'] ?? null;
        $status = $payload['status'] ?? 'delivered';

        if (! $externalId) {
            return null;
        }

        return new SmsStatusUpdate(
            externalId: (string) $externalId,
            status: (string) $status,
            failureCode: $payload['failure_code'] ?? null,
            failureReason: $payload['failure_reason'] ?? null,
            raw: $payload,
        );
    }
}

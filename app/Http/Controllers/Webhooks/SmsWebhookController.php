<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\Sms\ProcessSmsWebhookJob;
use App\Models\SmsWebhookEvent;
use App\Services\Sms\Providers\SmsMockProvider;
use App\Services\Sms\Providers\TwilioSmsProvider;
use App\Services\Sms\SmsProviderInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Webhook DLR SMS — pattern hardening Stripe / KYC.
 *
 * 1. Sélectionne provider depuis l'URL `{provider}`
 * 2. Parse + verify signature (provider-specific)
 * 3. Stocke l'event (idempotence sur external_event_id)
 * 4. Dispatch async, return 200
 */
class SmsWebhookController extends Controller
{
    public function handle(Request $request, string $provider): JsonResponse
    {
        $providerInstance = $this->resolveProvider($provider);
        if (! $providerInstance) {
            return response()->json(['ok' => false, 'error' => 'unknown provider'], 404);
        }

        $payload = $request->getContent();
        $headers = $request->headers->all();

        try {
            $parsed = $providerInstance->verifyWebhook($payload, $headers);
        } catch (\Throwable $e) {
            Log::warning('SmsWebhook: signature/parse failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['ok' => false, 'error' => 'invalid'], 400);
        }

        // External event id varies per provider — try common keys
        $externalEventId = $parsed['MessageSid']
            ?? $parsed['SmsSid']
            ?? $parsed['external_id']
            ?? $parsed['id']
            ?? null;

        if (! $externalEventId) {
            $externalEventId = $provider . '_' . Str::lower(Str::random(16));
        }

        $stored = SmsWebhookEvent::firstOrCreate(
            [
                'provider' => $provider,
                'external_event_id' => (string) $externalEventId,
            ],
            [
                'event_type' => $parsed['MessageStatus'] ?? $parsed['event_type'] ?? 'status',
                'payload' => $parsed,
                'status' => SmsWebhookEvent::STATUS_RECEIVED,
                'received_at' => now(),
            ]
        );

        if (in_array($stored->status, [SmsWebhookEvent::STATUS_RECEIVED, SmsWebhookEvent::STATUS_FAILED], true)) {
            ProcessSmsWebhookJob::dispatch($stored->id)->onQueue('sms-webhooks');
        }

        return response()->json([
            'ok' => true,
            'event_id' => $stored->id,
            'status' => $stored->status,
        ]);
    }

    protected function resolveProvider(string $name): ?SmsProviderInterface
    {
        return match ($name) {
            'mock' => new SmsMockProvider(),
            'twilio' => new TwilioSmsProvider(),
            default => null,
        };
    }
}

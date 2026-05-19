<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\Kyc\ProcessKycWebhookJob;
use App\Models\KycWebhookEvent;
use App\Services\Kyc\KycProviderInterface;
use App\Services\Kyc\Providers\KycMockProvider;
use App\Services\Kyc\Providers\OnfidoProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Webhook KYC — pattern hardening identique à Stripe v2 :
 *   1. Sélectionne le provider depuis l'URL `{provider}`
 *   2. Vérifie la signature
 *   3. Stocke l'event (idempotence sur external_event_id)
 *   4. Dispatch async, retourne 200
 */
class KycWebhookController extends Controller
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
            Log::warning('KycWebhook: signature/parse failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['ok' => false, 'error' => 'invalid'], 400);
        }

        $externalEventId = $parsed['id']
            ?? $parsed['event_id']
            ?? $parsed['payload']['object']['id']
            ?? null;

        if (! $externalEventId) {
            $externalEventId = $provider . '_' . Str::lower(Str::random(16));
        }

        $stored = KycWebhookEvent::firstOrCreate(
            [
                'provider' => $provider,
                'external_event_id' => (string) $externalEventId,
            ],
            [
                'event_type' => $parsed['action'] ?? $parsed['event_type'] ?? null,
                'payload' => $parsed,
                'status' => KycWebhookEvent::STATUS_RECEIVED,
                'received_at' => now(),
            ]
        );

        if (in_array($stored->status, [KycWebhookEvent::STATUS_RECEIVED, KycWebhookEvent::STATUS_FAILED], true)) {
            ProcessKycWebhookJob::dispatch($stored->id)->onQueue('kyc-webhooks');
        }

        return response()->json([
            'ok' => true,
            'event_id' => $stored->id,
            'status' => $stored->status,
        ]);
    }

    protected function resolveProvider(string $name): ?KycProviderInterface
    {
        return match ($name) {
            'mock' => new KycMockProvider(),
            'onfido' => new OnfidoProvider(),
            default => null,
        };
    }
}

<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\Kyc\ProcessKycWebhookJob;
use App\Models\KycWebhookEvent;
use App\Services\Kyc\KycProviderInterface;
use App\Services\Kyc\Providers\KycMockProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Webhook KYC — pattern hardening identique à Stripe v2 : 1. */
class KycWebhookController extends Controller
{
    public function handle(Request $request, string $provider): JsonResponse
    {
        $providerInstance = $this->resolveProvider($provider);
        if (! $providerInstance) {
            return response()->json(['ok' => false, 'error' => 'unknown provider'], 404);
        }

        // Le nom stocké vient de la configuration, jamais de l'URL : après la garde
        // les deux sont égaux, mais on ne réintroduit pas la donnée hostile.
        $providerName = $providerInstance->name();

        $payload = $request->getContent();
        $headers = $request->headers->all();

        try {
            $parsed = $providerInstance->verifyWebhook($payload, $headers);
        } catch (\Throwable $e) {
            Log::warning('KycWebhook: signature/parse failed', [
                'provider' => $providerName,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['ok' => false, 'error' => 'invalid'], 400);
        }

        $externalEventId = $parsed['id']
            ?? $parsed['event_id']
            ?? $parsed['payload']['object']['id']
            ?? null;

        if (! $externalEventId) {
            // L6 — deterministic fallback so a provider re-sending the SAME payload (e.g. after
            // a 5xx/timeout) dedupes on (provider, external_event_id) instead of being
            // reprocessed under a fresh random id each time.
            $externalEventId = $providerName.'_'.hash('sha256', (string) $payload);
        }

        $stored = KycWebhookEvent::firstOrCreate(
            [
                'provider' => $providerName,
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

    /** B5 — la seule source de vérité est la configuration (le conteneur). */
    protected function resolveProvider(string $name): ?KycProviderInterface
    {
        $configured = $this->providerConfigure();
        if (! $configured) {
            return null;
        }

        if ($configured->name() !== $name) {
            Log::warning('KycWebhook: segment d\'URL refusé (ne correspond pas au provider configuré)', [
                'segment_demande' => $name,
            ]);

            return null;
        }

        // Ceinture ET bretelles : même si la configuration désignait « mock » en
        // production (env oubliée), on refuse un vérificateur qui n'authentifie rien.
        if ($configured instanceof KycMockProvider && app()->isProduction()) {
            Log::critical('KycWebhook: tentative d\'atteindre le provider « mock » en production', [
                'segment_demande' => $name,
            ]);

            return null;
        }

        return $configured;
    }

    /** Le provider réellement configuré, ou null s'il n'est pas résoluble. */
    protected function providerConfigure(): ?KycProviderInterface
    {
        try {
            return app(KycProviderInterface::class);
        } catch (\Throwable $e) {
            Log::warning('KycWebhook: provider configuré non résoluble', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}

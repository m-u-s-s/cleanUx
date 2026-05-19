<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\Insurance\ProcessInsuranceWebhookJob;
use App\Models\InsuranceWebhookEvent;
use App\Services\Insurance\InsuranceProviderInterface;
use App\Services\Insurance\Providers\HiscoxInsuranceProvider;
use App\Services\Insurance\Providers\InsuranceMockProvider;
use App\Services\Insurance\Providers\WakamInsuranceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InsuranceWebhookController extends Controller
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
            Log::warning('InsuranceWebhook signature/parse failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['ok' => false, 'error' => 'invalid'], 400);
        }

        $externalEventId = $parsed['event_id']
            ?? $parsed['id']
            ?? $parsed['external_event_id']
            ?? ($provider . '_' . Str::lower(Str::random(16)));

        $stored = InsuranceWebhookEvent::firstOrCreate(
            ['provider' => $provider, 'external_event_id' => (string) $externalEventId],
            [
                'event_type' => $parsed['event_type'] ?? $parsed['type'] ?? 'unknown',
                'payload' => $parsed,
                'status' => InsuranceWebhookEvent::STATUS_RECEIVED,
                'received_at' => now(),
            ],
        );

        if (in_array($stored->status, [InsuranceWebhookEvent::STATUS_RECEIVED, InsuranceWebhookEvent::STATUS_FAILED], true)) {
            ProcessInsuranceWebhookJob::dispatch($stored->id)->onQueue('insurance-webhooks');
        }

        return response()->json([
            'ok' => true,
            'event_id' => $stored->id,
            'status' => $stored->status,
        ]);
    }

    protected function resolveProvider(string $name): ?InsuranceProviderInterface
    {
        return match ($name) {
            'mock'   => new InsuranceMockProvider(),
            'hiscox' => new HiscoxInsuranceProvider(),
            'wakam'  => new WakamInsuranceProvider(),
            default  => null,
        };
    }
}

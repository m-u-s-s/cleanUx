<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\Insurance\ProcessInsuranceWebhookJob;
use App\Models\InsuranceWebhookEvent;
use App\Services\Insurance\InsuranceProviderInterface;
use App\Services\Insurance\Providers\InsuranceMockProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * B5 — le segment {provider} de l'URL ne SÉLECTIONNE plus le vérificateur de
 * signature ; il ne fait que confirmer celui que la configuration a déjà choisi.
 * Laisser l'appelant désigner son vérificateur, c'est le laisser désigner
 * « aucune vérification » via /webhooks/insurance/mock.
 *
 * PORTE VOLONTAIREMENT STRICTE — décision assumée, pas un oubli.
 * On a soupçonné cette porte de casser /webhooks/insurance/hiscox en production, où
 * le modèle d'environnement livré disait INSURANCE_PROVIDER=mock. Elle ne casse rien
 * qui fonctionnait, pour trois raisons vérifiables :
 *   1. le provider est un binding GLOBAL unique (InsuranceServiceProvider) : avec
 *      « mock » configuré, aucune police n'est jamais souscrite chez Hiscox, donc
 *      Hiscox n'a aucune raison de nous notifier quoi que ce soit ;
 *   2. même avant ce lot, cette URL ne marchait pas de bout en bout :
 *      ProcessInsuranceWebhookJob::handle() type-hinte InsuranceProviderInterface et
 *      reçoit donc le provider CONFIGURÉ. Une charge utile Hiscox (event_type /
 *      policy_id) était mappée par InsuranceMockProvider::mapWebhookEvent(), qui
 *      exige target/external_id/status : l'événement finissait « ignored ».
 *      Assouplir la porte restaurerait un stockage inutile, pas une intégration ;
 *   3. « mock » en production n'est pas une configuration à accommoder, c'est une
 *      configuration à interdire : InsuranceMockProvider::purchase() rend « accepté »
 *      avec un numéro de police inventé — le client paierait une assurance qui
 *      n'existe chez personne. Le correctif est donc dans .env.production.example.
 *
 * Conséquence opérationnelle à connaître lors d'un CHANGEMENT de provider : pendant
 * la bascule, l'ancien assureur peut encore notifier des polices en cours et ces
 * webhooks seront refusés (404). Il faut vider les polices en cours avant de changer
 * INSURANCE_PROVIDER, pas rouvrir la porte.
 */
class InsuranceWebhookController extends Controller
{
    public function handle(Request $request, string $provider): JsonResponse
    {
        $providerInstance = $this->resolveProvider($provider);
        if (! $providerInstance) {
            return response()->json(['ok' => false, 'error' => 'unknown provider'], 404);
        }

        // Le nom stocké vient de la configuration, jamais de l'URL.
        $providerName = $providerInstance->name();

        $payload = $request->getContent();
        $headers = $request->headers->all();

        try {
            $parsed = $providerInstance->verifyWebhook($payload, $headers);
        } catch (\Throwable $e) {
            Log::warning('InsuranceWebhook signature/parse failed', [
                'provider' => $providerName,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['ok' => false, 'error' => 'invalid'], 400);
        }

        $externalEventId = $parsed['event_id']
            ?? $parsed['id']
            ?? $parsed['external_event_id']
            // L6 — deterministic fallback so re-sent identical payloads dedupe.
            ?? ($providerName.'_'.hash('sha256', (string) $payload));

        $stored = InsuranceWebhookEvent::firstOrCreate(
            ['provider' => $providerName, 'external_event_id' => (string) $externalEventId],
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

    /**
     * B5 — la seule source de vérité est la configuration (le conteneur). Le segment
     * d'URL doit lui être ÉGAL, sinon 404 sans révéler le provider réellement configuré.
     */
    protected function resolveProvider(string $name): ?InsuranceProviderInterface
    {
        $configured = $this->providerConfigure();
        if (! $configured) {
            return null;
        }

        if ($configured->name() !== $name) {
            Log::warning('InsuranceWebhook: segment d\'URL refusé (ne correspond pas au provider configuré)', [
                'segment_demande' => $name,
            ]);

            return null;
        }

        if ($configured instanceof InsuranceMockProvider && app()->isProduction()) {
            Log::critical('InsuranceWebhook: tentative d\'atteindre le provider « mock » en production', [
                'segment_demande' => $name,
            ]);

            return null;
        }

        return $configured;
    }

    /** Le provider réellement configuré, ou null s'il n'est pas résoluble. */
    protected function providerConfigure(): ?InsuranceProviderInterface
    {
        try {
            return app(InsuranceProviderInterface::class);
        } catch (\Throwable $e) {
            Log::warning('InsuranceWebhook: provider configuré non résoluble', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}

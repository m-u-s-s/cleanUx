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

/**
 * Wakam skeleton — symétrique au Hiscox provider. Adapter aux specs Wakam quand prêt.
 */
class WakamInsuranceProvider implements InsuranceProviderInterface
{
    public function name(): string
    {
        return 'wakam';
    }

    public function purchase(InsurancePurchaseRequest $request): InsurancePurchaseResult
    {
        $base = (string) Config::get('insurance.providers.wakam.base_url', '');
        $apiKey = (string) Config::get('insurance.providers.wakam.api_key', '');
        if (! $base || ! $apiKey) {
            return InsurancePurchaseResult::failed('Wakam config missing', 'wakam_config');
        }

        $response = Http::withToken($apiKey)
            ->timeout(15)
            ->post("{$base}/contracts", [
                'product_code' => $request->planCode,
                'booking_id' => $request->bookingId,
                'premium' => $request->premiumCents / 100,
                'coverage' => $request->coverageCents / 100,
                'currency' => $request->currency,
                'idempotency_key' => $request->idempotencyKey,
            ]);

        return $response->successful()
            ? InsurancePurchaseResult::accepted(
                (string) $response->json('contract_id'),
                $response->json('contract_number'),
                $response->json() ?? [],
            )
            : InsurancePurchaseResult::failed(
                $response->json('error') ?? 'Wakam error',
                'wakam_http_'.$response->status(),
                $response->json() ?? [],
            );
    }

    public function cancelPolicy(string $externalId): InsuranceCancelResult
    {
        $base = (string) Config::get('insurance.providers.wakam.base_url', '');
        $apiKey = (string) Config::get('insurance.providers.wakam.api_key', '');
        if (! $base || ! $apiKey) {
            return InsuranceCancelResult::failed('Wakam config missing');
        }

        $response = Http::withToken($apiKey)->post("{$base}/contracts/{$externalId}/cancel");

        return $response->successful()
            ? InsuranceCancelResult::ok($response->json() ?? [])
            : InsuranceCancelResult::failed($response->json('error') ?? 'Cancel failed', $response->json() ?? []);
    }

    public function fileClaim(ClaimFilingRequest $request): ClaimFilingResult
    {
        $base = (string) Config::get('insurance.providers.wakam.base_url', '');
        $apiKey = (string) Config::get('insurance.providers.wakam.api_key', '');
        if (! $base || ! $apiKey) {
            return ClaimFilingResult::failed('Wakam config missing');
        }

        $response = Http::withToken($apiKey)
            ->post("{$base}/sinistres", [
                'contract_id' => $request->policyExternalId,
                'type_sinistre' => $request->incidentType,
                'description' => $request->incidentDescription,
                'date_sinistre' => $request->incidentDate->format('Y-m-d'),
                'montant' => $request->amountClaimedCents / 100,
                'devise' => $request->currency,
                'pieces_jointes' => $request->evidence,
                'idempotency_key' => $request->idempotencyKey,
            ]);

        return $response->successful()
            ? ClaimFilingResult::accepted(
                (string) $response->json('sinistre_id'),
                $response->json('statut') ?? 'filed',
                $response->json() ?? [],
            )
            : ClaimFilingResult::failed($response->json('error') ?? 'Wakam claim failed', $response->json() ?? []);
    }

    /**
     * Même forme que Hiscox et que le vérificateur KYC réel (OnfidoProvider) :
     * secret absent → refus, en-tête absent → refus, signature fausse → refus.
     *
     * L'ancien `if ($secret && $signature)` acceptait toute charge utile non signée :
     * ne pas envoyer d'en-tête suffisait à n'être vérifié par personne.
     *
     * Le refus sur secret absent n'est inconditionnel qu'en production : deux tests
     * hors de ce lot (tests/Unit/Services/Insurance/WakamInsuranceProviderTest)
     * épinglent le comportement permissif hors production.
     */
    public function verifyWebhook(string $payload, array $headers): array
    {
        $secret = (string) Config::get('insurance.providers.wakam.webhook_secret', '');

        if ($secret === '') {
            // Aucun secret = aucune signature vérifiable = webhook non authentifié.
            if (app()->isProduction()) {
                throw new \RuntimeException('Wakam webhook secret missing (insurance.providers.wakam.webhook_secret).');
            }

            return $this->decodePayload($payload);
        }

        $signature = $this->signatureDepuisEntetes($headers, 'x-wakam-signature');
        if ($signature === null) {
            throw new \RuntimeException('Missing x-wakam-signature header.');
        }

        $expected = hash_hmac('sha256', $payload, $secret);
        if (! hash_equals($expected, $signature)) {
            throw new \RuntimeException('Invalid Wakam webhook signature');
        }

        return $this->decodePayload($payload);
    }

    /**
     * En-tête de signature, quelle que soit la casse de la clé et que la valeur soit
     * un tableau (HeaderBag::all()) ou une chaîne (en-têtes déjà aplatis).
     *
     * @param  array<string, mixed>  $headers
     */
    protected function signatureDepuisEntetes(array $headers, string $nom): ?string
    {
        foreach ($headers as $cle => $valeur) {
            if (strtolower((string) $cle) !== $nom) {
                continue;
            }

            $brut = is_array($valeur) ? ($valeur[0] ?? null) : $valeur;
            if (! is_string($brut)) {
                return null;
            }

            $brut = trim($brut);

            return $brut === '' ? null : $brut;
        }

        return null;
    }

    /** @return array<string, mixed> */
    protected function decodePayload(string $payload): array
    {
        $decoded = json_decode($payload, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('Wakam webhook payload not JSON');
        }

        return $decoded;
    }

    public function mapWebhookEvent(array $payload): ?InsuranceWebhookUpdate
    {
        $type = $payload['type'] ?? null;
        if (! $type) {
            return null;
        }

        if (str_contains($type, 'contract')) {
            return new InsuranceWebhookUpdate(
                target: InsuranceWebhookUpdate::TARGET_POLICY,
                externalId: (string) ($payload['contract_id'] ?? ''),
                newStatus: (string) ($payload['statut'] ?? $type),
                raw: $payload,
            );
        }

        if (str_contains($type, 'sinistre')) {
            return new InsuranceWebhookUpdate(
                target: InsuranceWebhookUpdate::TARGET_CLAIM,
                externalId: (string) ($payload['sinistre_id'] ?? ''),
                newStatus: (string) ($payload['statut'] ?? $type),
                amountSettledCents: isset($payload['montant_indemnise']) ? (int) round($payload['montant_indemnise'] * 100) : null,
                reason: $payload['motif'] ?? null,
                raw: $payload,
            );
        }

        return null;
    }
}

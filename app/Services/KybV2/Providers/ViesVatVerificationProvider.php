<?php

namespace App\Services\KybV2\Providers;

use App\Services\KybV2\Contracts\BusinessVerificationProviderContract;
use App\Services\KybV2\VerificationResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * VIES (VAT Information Exchange System) — validation TVA intracom UE.
 * Endpoint REST officiel : https://ec.europa.eu/taxation_customs/vies/rest-api/ms/{COUNTRY}/vat/{VAT}
 * Pas de clé API requise (gratuit + rate-limited côté Commission Européenne).
 */
class ViesVatVerificationProvider implements BusinessVerificationProviderContract
{
    public function name(): string
    {
        return 'vies';
    }

    public function verifyIdentifier(string $identifierType, string $identifierValue, ?string $countryCode = null): VerificationResult
    {
        // VIES uniquement pour TVA — déléguer
        return new VerificationResult(false, 'vies', 'identity', error: 'use_identity_provider');
    }

    public function verifyVatId(string $vatId, ?string $countryCode = null): VerificationResult
    {
        $clean = strtoupper(preg_replace('/\s+/', '', trim($vatId)));
        if (! preg_match('/^([A-Z]{2})([A-Z0-9]{8,12})$/', $clean, $matches)) {
            return new VerificationResult(false, 'vies', 'tax_validity', error: 'vat_format_invalid');
        }
        $country = $matches[1];
        $number = $matches[2];

        try {
            $cfg = (array) config('kyb_v2.providers.vies');
            $base = rtrim($cfg['endpoint'] ?? 'https://ec.europa.eu/taxation_customs/vies/rest-api', '/');
            $response = Http::acceptJson()
                ->timeout(15)
                ->get("{$base}/ms/{$country}/vat/{$number}");
            if (! $response->successful()) {
                return new VerificationResult(false, 'vies', 'tax_validity', error: 'http_' . $response->status());
            }
            $json = $response->json();
            $isValid = (bool) ($json['isValid'] ?? false);
            return new VerificationResult(
                success: $isValid,
                provider: 'vies',
                checkType: 'tax_validity',
                matchedValue: $clean,
                payload: $json,
                error: $isValid ? null : 'vat_not_valid',
            );
        } catch (\Throwable $e) {
            Log::warning('[kyb_v2] vies error', ['error' => $e->getMessage()]);
            return new VerificationResult(false, 'vies', 'tax_validity', error: 'exception:' . mb_substr($e->getMessage(), 0, 200));
        }
    }
}

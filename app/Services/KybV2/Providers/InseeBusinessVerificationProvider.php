<?php

namespace App\Services\KybV2\Providers;

use App\Services\KybV2\Contracts\BusinessVerificationProviderContract;
use App\Services\KybV2\VerificationResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * INSEE Sirene V3 (France). Squelette — nécessite INSEE_API_KEY.
 * Doc : https://api.insee.fr/catalogue/site/themes/wso2/subthemes/insee/pages/item-info.jag?name=Sirene&version=V3
 */
class InseeBusinessVerificationProvider implements BusinessVerificationProviderContract
{
    public function name(): string
    {
        return 'insee';
    }

    public function verifyIdentifier(string $identifierType, string $identifierValue, ?string $countryCode = null): VerificationResult
    {
        $cfg = (array) config('kyb_v2.providers.insee');
        $apiKey = $cfg['api_key'] ?? null;
        if (! $apiKey) {
            return new VerificationResult(false, 'insee', 'identity', error: 'insee_not_configured');
        }
        $clean = preg_replace('/\s+/', '', $identifierValue);
        $path = match ($identifierType) {
            'siret' => "/siret/{$clean}",
            'siren' => "/siren/{$clean}",
            default => null,
        };
        if (! $path) {
            return new VerificationResult(false, 'insee', 'identity', error: 'unsupported_identifier_type');
        }
        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(10)
                ->get(rtrim($cfg['endpoint'], '/') . $path);
            if ($response->status() === 404) {
                return new VerificationResult(false, 'insee', 'identity', error: 'not_found');
            }
            if (! $response->successful()) {
                return new VerificationResult(false, 'insee', 'identity', error: 'http_' . $response->status());
            }
            $json = $response->json();
            $etab = $identifierType === 'siret'
                ? ($json['etablissement'] ?? null)
                : ($json['uniteLegale'] ?? null);
            if (! $etab) {
                return new VerificationResult(false, 'insee', 'identity', error: 'empty_response');
            }
            return new VerificationResult(
                success: true,
                provider: 'insee',
                checkType: 'identity',
                matchedValue: $clean,
                payload: $json,
            );
        } catch (\Throwable $e) {
            Log::warning('[kyb_v2] insee error', ['error' => $e->getMessage()]);
            return new VerificationResult(false, 'insee', 'identity', error: 'exception:' . mb_substr($e->getMessage(), 0, 200));
        }
    }

    public function verifyVatId(string $vatId, ?string $countryCode = null): VerificationResult
    {
        // INSEE ne fait pas la TVA — on delègue à VIES
        return new VerificationResult(false, 'insee', 'tax_validity', error: 'use_vies_provider');
    }
}

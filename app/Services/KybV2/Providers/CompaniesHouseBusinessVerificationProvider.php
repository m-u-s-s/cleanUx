<?php

namespace App\Services\KybV2\Providers;

use App\Services\KybV2\Contracts\BusinessVerificationProviderContract;
use App\Services\KybV2\VerificationResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * UK Companies House (gratuit avec inscription).
 * Doc : https://developer.company-information.service.gov.uk/
 */
class CompaniesHouseBusinessVerificationProvider implements BusinessVerificationProviderContract
{
    public function name(): string
    {
        return 'companies_house';
    }

    public function verifyIdentifier(string $identifierType, string $identifierValue, ?string $countryCode = null): VerificationResult
    {
        if ($identifierType !== 'companies_house') {
            return new VerificationResult(false, 'companies_house', 'identity', error: 'unsupported_identifier_type');
        }
        $cfg = (array) config('kyb_v2.providers.companies_house');
        $apiKey = $cfg['api_key'] ?? null;
        if (! $apiKey) {
            return new VerificationResult(false, 'companies_house', 'identity', error: 'companies_house_not_configured');
        }
        try {
            $clean = strtoupper(preg_replace('/\s+/', '', $identifierValue));
            $response = Http::withBasicAuth($apiKey, '')
                ->acceptJson()
                ->timeout(10)
                ->get(rtrim($cfg['endpoint'], '/') . "/company/{$clean}");
            if ($response->status() === 404) {
                return new VerificationResult(false, 'companies_house', 'identity', error: 'not_found');
            }
            if (! $response->successful()) {
                return new VerificationResult(false, 'companies_house', 'identity', error: 'http_' . $response->status());
            }
            return new VerificationResult(
                success: true,
                provider: 'companies_house',
                checkType: 'identity',
                matchedValue: $clean,
                payload: $response->json(),
            );
        } catch (\Throwable $e) {
            Log::warning('[kyb_v2] companies_house error', ['error' => $e->getMessage()]);
            return new VerificationResult(false, 'companies_house', 'identity', error: 'exception:' . mb_substr($e->getMessage(), 0, 200));
        }
    }

    public function verifyVatId(string $vatId, ?string $countryCode = null): VerificationResult
    {
        return new VerificationResult(false, 'companies_house', 'tax_validity', error: 'use_vies_provider');
    }
}

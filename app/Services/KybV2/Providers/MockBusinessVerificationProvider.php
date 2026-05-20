<?php

namespace App\Services\KybV2\Providers;

use App\Services\KybV2\Contracts\BusinessVerificationProviderContract;
use App\Services\KybV2\VerificationResult;

/**
 * Mock provider — données canned pour CI/dev. Match SIRET français + KBO belges courants.
 *
 * Convention :
 *   - tout identifier valide commence par "VALID-"
 *   - identifier commençant par "FAIL-" simule une vérification échouée
 *   - sinon valide via checksum modulo de longueur (simulé)
 */
class MockBusinessVerificationProvider implements BusinessVerificationProviderContract
{
    /** @var array<string, array{legal_name:string, legal_form:string, address:array, vat_id:?string, incorporation_date:?string}> */
    protected static array $catalog = [
        '12345678900012' => [
            'legal_name' => 'Acme Cleaning SARL',
            'legal_form' => 'SARL',
            'address' => ['street' => '12 rue de Paris', 'postal' => '75001', 'city' => 'Paris', 'country' => 'FR'],
            'vat_id' => 'FR12345678900',
            'incorporation_date' => '2018-03-15',
        ],
        '0123456789' => [
            'legal_name' => 'Demo Belgian Cleaning BVBA',
            'legal_form' => 'BVBA',
            'address' => ['street' => 'Rue Royale 50', 'postal' => '1000', 'city' => 'Bruxelles', 'country' => 'BE'],
            'vat_id' => 'BE0123456789',
            'incorporation_date' => '2020-06-01',
        ],
    ];

    public function name(): string
    {
        return 'mock';
    }

    public function verifyIdentifier(string $identifierType, string $identifierValue, ?string $countryCode = null): VerificationResult
    {
        $clean = preg_replace('/\s+/', '', trim($identifierValue));

        if (str_starts_with(strtoupper($clean), 'FAIL-')) {
            return new VerificationResult(
                success: false,
                provider: 'mock',
                checkType: 'identity',
                error: 'mock_forced_failure',
            );
        }

        // Try catalog first
        if (isset(self::$catalog[$clean])) {
            $entry = self::$catalog[$clean];
            return new VerificationResult(
                success: true,
                provider: 'mock',
                checkType: 'identity',
                matchedValue: $clean,
                payload: [
                    'identifier_type' => $identifierType,
                    'identifier_value' => $clean,
                    'legal_name' => $entry['legal_name'],
                    'legal_form' => $entry['legal_form'],
                    'registered_address' => $entry['address'],
                    'vat_id' => $entry['vat_id'],
                    'incorporation_date' => $entry['incorporation_date'],
                    'is_active' => true,
                ],
            );
        }

        // Fallback : checksum simulé (longueur 9-14 = valide)
        $len = strlen($clean);
        if (str_starts_with(strtoupper($clean), 'VALID-') || ($len >= 9 && $len <= 14 && ctype_digit($clean))) {
            return new VerificationResult(
                success: true,
                provider: 'mock',
                checkType: 'identity',
                matchedValue: $clean,
                payload: [
                    'identifier_type' => $identifierType,
                    'identifier_value' => $clean,
                    'legal_name' => 'Generic Business ' . substr($clean, -4),
                    'is_active' => true,
                ],
            );
        }

        return new VerificationResult(
            success: false,
            provider: 'mock',
            checkType: 'identity',
            error: 'identifier_not_found',
        );
    }

    public function verifyVatId(string $vatId, ?string $countryCode = null): VerificationResult
    {
        $clean = strtoupper(preg_replace('/\s+/', '', trim($vatId)));
        if (str_starts_with($clean, 'FAIL')) {
            return new VerificationResult(false, 'mock', 'tax_validity', error: 'vat_invalid');
        }
        // Pattern : 2 lettres pays + 8-12 chiffres/alphanum
        if (! preg_match('/^[A-Z]{2}[A-Z0-9]{8,12}$/', $clean)) {
            return new VerificationResult(false, 'mock', 'tax_validity', error: 'vat_format_invalid');
        }
        return new VerificationResult(
            success: true,
            provider: 'mock',
            checkType: 'tax_validity',
            matchedValue: $clean,
            payload: ['vat_id' => $clean, 'valid' => true],
        );
    }
}

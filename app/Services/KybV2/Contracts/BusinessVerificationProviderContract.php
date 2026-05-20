<?php

namespace App\Services\KybV2\Contracts;

use App\Services\KybV2\VerificationResult;

interface BusinessVerificationProviderContract
{
    public function name(): string;

    /**
     * Vérifie l'identité légale d'une entreprise.
     * Provider doit soft-fail (jamais throw) — retourne VerificationResult avec success=false.
     */
    public function verifyIdentifier(string $identifierType, string $identifierValue, ?string $countryCode = null): VerificationResult;

    /**
     * Vérifie la validité d'un numéro de TVA intracom (VIES).
     */
    public function verifyVatId(string $vatId, ?string $countryCode = null): VerificationResult;
}

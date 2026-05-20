<?php

namespace App\Services\KybV2\Contracts;

use App\Services\KybV2\SanctionsResult;

interface SanctionsScreeningProviderContract
{
    public function name(): string;

    /**
     * Screen un nom d'entité (ou de personne) contre une liste sanctions.
     */
    public function screen(string $nameOrIdentifier, string $listName, ?string $countryCode = null): SanctionsResult;
}

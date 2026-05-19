<?php

namespace App\Services\Fx;

interface FxProviderInterface
{
    public function name(): string;

    /**
     * Récupère un set de taux pour la base donnée et la liste des quote currencies.
     *
     * @param array<int,string> $quotes
     * @return array<int, FxRate>
     */
    public function fetchRates(string $base, array $quotes): array;

    /**
     * @return array<int, string>
     */
    public function supportedBases(): array;
}

<?php

namespace App\Services\ApiTokensV2;

use App\Models\ApiTokenScope;

class ScopeRegistry
{
    /**
     * Liste des codes scope autorisés (whitelist config + DB).
     */
    public function allowedCodes(): array
    {
        $configCodes = (array) config('api_tokens_v2.allowed_scopes', []);
        $dbCodes = ApiTokenScope::query()->active()->pluck('code')->all();
        return array_values(array_unique(array_merge($configCodes, $dbCodes)));
    }

    /**
     * Filtre le tableau de scopes demandés pour ne garder que ceux autorisés
     * pour le role donné. Retourne les codes valides + invalides séparés.
     *
     * @return array{valid: string[], invalid: string[]}
     */
    public function filterForRole(array $requested, ?string $role): array
    {
        $allowed = $this->allowedCodes();
        $valid = [];
        $invalid = [];
        foreach (array_unique($requested) as $code) {
            $code = (string) $code;
            if (! in_array($code, $allowed, true)) {
                $invalid[] = $code;
                continue;
            }
            $scope = ApiTokenScope::query()->where('code', $code)->first();
            if ($scope && $scope->required_role && $scope->required_role !== $role) {
                // exception : 'admin' peut tout détenir
                if ($role !== 'admin') {
                    $invalid[] = $code;
                    continue;
                }
            }
            $valid[] = $code;
        }
        return ['valid' => $valid, 'invalid' => $invalid];
    }

    public function isDangerous(string $code): bool
    {
        if (in_array($code, (array) config('api_tokens_v2.dangerous_scopes', []), true)) {
            return true;
        }
        return (bool) ApiTokenScope::query()->where('code', $code)->where('is_dangerous', true)->exists();
    }

    /**
     * Le scope 'admin:everything' couvre tous les scopes.
     */
    public function tokenHasScope(array $tokenScopes, string $required): bool
    {
        if (in_array('admin:everything', $tokenScopes, true)) {
            return true;
        }
        return in_array($required, $tokenScopes, true);
    }

    public function activeScopes(): array
    {
        return ApiTokenScope::query()->active()->orderBy('category')->orderBy('code')->get()->all();
    }
}

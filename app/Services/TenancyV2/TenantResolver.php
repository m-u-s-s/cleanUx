<?php

namespace App\Services\TenancyV2;

use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TenantResolver
{
    /**
     * Résout un Tenant à partir de la requête en tentant les strategies config.
     * Retourne null si rien ne résout (mode platform-wide ou erreur silencieuse).
     */
    public function resolve(Request $request): ?Tenant
    {
        if (! (bool) config('tenancy_v2.enabled', true)) {
            return null;
        }
        $strategies = (array) config('tenancy_v2.resolution_strategies', ['header', 'subdomain']);
        foreach ($strategies as $strategy) {
            $tenant = match ($strategy) {
                'header' => $this->resolveByHeader($request),
                'subdomain' => $this->resolveBySubdomain($request),
                'path' => $this->resolveByPath($request),
                'domain' => $this->resolveByDomain($request),
                default => null,
            };
            if ($tenant) {
                return $tenant;
            }
        }
        return $this->resolveDefault();
    }

    protected function resolveByHeader(Request $request): ?Tenant
    {
        $headerName = (string) config('tenancy_v2.header_name', 'X-Tenant-Code');
        $code = $request->header($headerName);
        if (! $code) {
            return null;
        }
        return Tenant::query()->where('code', $code)->usable()->first();
    }

    protected function resolveBySubdomain(Request $request): ?Tenant
    {
        $host = (string) $request->getHost();
        $pattern = (string) config('tenancy_v2.subdomain_pattern', '/^([a-z0-9-]+)\.(.+\..+)$/i');
        if (! @preg_match($pattern, $host, $matches)) {
            return null;
        }
        $subdomain = strtolower((string) ($matches[1] ?? ''));
        if ($subdomain === '') {
            return null;
        }
        $reserved = (array) config('tenancy_v2.reserved_subdomains', []);
        if (in_array($subdomain, $reserved, true)) {
            return null;
        }
        return Tenant::query()->where('code', $subdomain)->orWhere('slug', $subdomain)->usable()->first();
    }

    protected function resolveByPath(Request $request): ?Tenant
    {
        $segments = $request->segments();
        $first = $segments[0] ?? null;
        if (! $first) {
            return null;
        }
        return Tenant::query()->where('slug', strtolower($first))->usable()->first();
    }

    protected function resolveByDomain(Request $request): ?Tenant
    {
        $host = strtolower((string) $request->getHost());
        $domain = TenantDomain::query()
            ->where('domain', $host)
            ->verified()
            ->first();
        if (! $domain) {
            return null;
        }
        $tenant = $domain->tenant;
        return $tenant && $tenant->isUsable() ? $tenant : null;
    }

    protected function resolveDefault(): ?Tenant
    {
        $defaultCode = (string) config('tenancy_v2.default_tenant_code', 'main');
        if ($defaultCode === '') {
            return null;
        }
        try {
            return Tenant::query()->where('code', $defaultCode)->usable()->first();
        } catch (\Throwable $e) {
            Log::warning('[tenancy_v2] default tenant resolve failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}

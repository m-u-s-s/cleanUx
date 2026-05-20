<?php

namespace App\Http\Middleware\TenancyV2;

use App\Services\TenancyV2\TenantContext;
use App\Services\TenancyV2\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware résoud le tenant courant et le pousse dans TenantContext.
 *
 * Usage :
 *   ->middleware('tenant')                  # résoud mais accepte null (platform-wide ok)
 *   ->middleware('tenant.required')         # 404 si pas de tenant résolu
 *   ->middleware('tenant.active')           # 503 si tenant suspendu
 */
class ResolveTenant
{
    public function __construct(
        protected TenantResolver $resolver,
        protected TenantContext $context,
    ) {}

    public function handle(Request $request, Closure $next, string $mode = 'optional'): Response
    {
        $tenant = $this->resolver->resolve($request);

        if (! $tenant) {
            if ($mode === 'required') {
                return response()->json([
                    'ok' => false,
                    'error' => 'tenant_not_found',
                ], 404);
            }
            $this->context->set(null);
            return $next($request);
        }

        if ($mode === 'active' && $tenant->isSuspended()) {
            return response()->json([
                'ok' => false,
                'error' => 'tenant_suspended',
                'reason' => $tenant->suspended_reason,
            ], 503);
        }

        $this->context->set($tenant);
        return $next($request);
    }
}

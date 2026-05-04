<?php

namespace App\Http\Middleware;

use App\Models\OrganizationAccount;
use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de vérification des permissions sur une organisation.
 *
 * Usage dans les routes :
 *   ->middleware('org.permission:bookings.create')
 *   ->middleware('org.permission:finance.view,analytics.view')  // OU logique
 *
 * L'organisation est résolue depuis :
 *   1. Le paramètre de route {organization}
 *   2. L'organisation courante de l'utilisateur (current_organization_id)
 */
class CheckOrganizationPermission
{
    public function __construct(private PermissionService $permissions)
    {
    }

    public function handle(Request $request, Closure $next, string ...$requiredPermissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        // Les admins plateforme passent toujours
        if ($user->isPlatformAdmin()) {
            return $next($request);
        }

        // Résoudre l'organisation cible
        $org = $this->resolveOrganization($request, $user);

        if (! $org) {
            abort(403, 'Aucune organisation active.');
        }

        // Vérifier au moins une des permissions requises (OR)
        foreach ($requiredPermissions as $permission) {
            if ($this->permissions->can($user, $permission, $org)) {
                return $next($request);
            }
        }

        abort(403, 'Permission insuffisante.');
    }

    private function resolveOrganization(Request $request, $user): ?OrganizationAccount
    {
        // 1. Depuis le paramètre de route
        if ($request->route('organization')) {
            $param = $request->route('organization');

            return $param instanceof OrganizationAccount
                ? $param
                : OrganizationAccount::find($param);
        }

        // 2. Organisation courante de l'utilisateur
        if ($user->current_organization_id) {
            return $user->currentOrganization;
        }

        return null;
    }
}

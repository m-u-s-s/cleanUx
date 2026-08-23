<?php

namespace App\Http\Middleware;

use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Middleware de vérification des permissions sur une organisation. */
class CheckOrganizationPermission
{
    public function __construct(private PermissionService $permissions) {}

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

    /** L'ORGANISATION SE RÉSOUT PAR `organizationContextId()`, ET L'ADHÉSION EST VÉRIFIÉE. */
    private function resolveOrganization(Request $request, $user): ?OrganizationAccount
    {
        // 1. Depuis le paramètre de route — c'est une cible explicite, pas une déduction.
        if ($request->route('organization')) {
            $param = $request->route('organization');

            $organisation = $param instanceof OrganizationAccount
                ? $param
                : OrganizationAccount::find($param);

            return $this->adhesionActive($user, $organisation) ? $organisation : null;
        }

        $organisationId = method_exists($user, 'organizationContextId')
            ? $user->organizationContextId()
            : $user->current_organization_id;

        if ($organisationId === null) {
            return null;
        }

        $organisation = OrganizationAccount::find($organisationId);

        return $this->adhesionActive($user, $organisation) ? $organisation : null;
    }

    private function adhesionActive(User $user, ?OrganizationAccount $organisation): bool
    {
        if ($organisation === null) {
            return false;
        }

        return OrganizationMember::query()
            ->where('organization_account_id', $organisation->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }
}

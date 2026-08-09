<?php

namespace App\Http\Middleware;

use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
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

    /**
     * L'ORGANISATION SE RÉSOUT PAR `organizationContextId()`, ET L'ADHÉSION EST VÉRIFIÉE.
     *
     * Deux corrections par rapport à la version d'origine :
     *
     * 1. `current_organization_id` seul ne suffit pas. C'est un repli parmi quatre —
     *    `organizationContextId()` regarde d'abord `organization_account_id`, puis les métadonnées.
     *    Les seeders ont déjà rempli une colonne et pas l'autre, et tout l'espace société
     *    répondait 403 pour cette seule raison.
     *
     * 2. Une organisation résolue ne prouve PAS l'appartenance. Un compte qui garde en base
     *    l'identifiant d'une société qu'il a quittée verrait ses permissions évaluées contre elle.
     *    On exige donc une adhésion ACTIVE — le rôle qui sert à évaluer la permission doit exister.
     */
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

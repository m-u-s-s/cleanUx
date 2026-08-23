<?php

namespace App\Http\Middleware;

use App\Enums\OrganizationType;
use App\Models\OrganizationAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Garde de TYPE d'organisation pour les dashboards entreprise. */
class EnsureOrganizationType
{
    public function handle(Request $request, Closure $next, string $expected): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        // Les admins plateforme passent toujours.
        if ($user->isPlatformAdmin()) {
            return $next($request);
        }

        // L'organisation active vit dans DEUX colonnes (`organization_account_id`
        // et `current_organization_id`), plus un repli dans `metadata`.
        // `organizationContextId()` est la résolution unique du dépôt : lire
        // `current_organization_id` seul refusait des membres légitimes.
        $orgId = $user->organizationContextId();
        abort_if(empty($orgId), 403, 'Aucune organisation active.');

        // `organization_accounts.type` est une colonne string non castée :
        // on la résout via une requête typée puis on la mappe sur l'enum.
        $rawType = OrganizationAccount::query()->whereKey($orgId)->value('type');
        $type = OrganizationType::tryFrom((string) $rawType);
        abort_if($type === null, 403, 'Type d’organisation inconnu.');

        $ok = match ($expected) {
            'client' => $type->isClient(),
            'provider' => $type->isProvider(),
            default => false,
        };

        abort_unless($ok, 403, 'Cet espace ne correspond pas au type de votre organisation.');

        return $next($request);
    }
}

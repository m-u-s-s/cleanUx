<?php

namespace App\Http\Middleware;

use App\Enums\OrganizationType;
use App\Models\OrganizationAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde de TYPE d'organisation pour les dashboards entreprise.
 *
 * Le trait EnforcesActiveOrgMembership (composants Livewire) prouve déjà
 * l'appartenance à l'org courante (anti-IDOR horizontal). Ce middleware ajoute
 * la couche manquante : vérifier que le TYPE de l'org courante correspond à la
 * surface demandée, pour qu'un membre d'une provider_company ne charge pas l'UI
 * client_company (et inversement).
 *
 * Usage dans les routes :
 *   ->middleware('org.type:client')     // CLIENT_COMPANY ou HYBRID
 *   ->middleware('org.type:provider')   // PROVIDER_COMPANY / PROVIDER_SOLO ou HYBRID
 */
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

        $orgId = $user->current_organization_id;
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

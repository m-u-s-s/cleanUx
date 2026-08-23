<?php

namespace App\Support\Finance;

use App\Models\User;
use App\Services\Enterprise\EnterpriseRoutingService;
use Illuminate\Database\Eloquent\Builder;

/** Single source of truth for scoping client-facing finance documents (invoices/quotes) to what a given client may see — own documents plus, for enterprise clients, their organization's documents honoring site-scope. */
class ClientFinanceDocumentScope
{
    /**
     * LE TYPE DU MODÈLE TRAVERSE LA PORTÉE.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, User $user, string $relationPath = 'rendezVous'): Builder
    {
        $allowedSiteIds = app(EnterpriseRoutingService::class)->allowedSiteIdsForUser($user);
        $siteScope = (string) data_get($user->metadata, 'entreprise_context.site_scope', 'all');
        $organizationId = (int) ($user->organization_account_id ?? 0);

        return $query->where(function (Builder $scoped) use ($user, $allowedSiteIds, $siteScope, $organizationId, $relationPath) {
            $scoped->where('client_id', $user->id);

            if ($organizationId > 0) {
                $scoped->orWhere(function (Builder $orgQuery) use ($organizationId, $allowedSiteIds, $siteScope, $relationPath) {
                    $orgQuery->where('organization_account_id', $organizationId);

                    if ($siteScope === 'selected') {
                        if ($allowedSiteIds === []) {
                            $orgQuery->whereRaw('1 = 0');
                        } else {
                            $orgQuery->whereHas($relationPath, function (Builder $rdvQuery) use ($allowedSiteIds) {
                                $rdvQuery->whereIn('organization_site_id', $allowedSiteIds);
                            });
                        }
                    }
                });
            }
        });
    }
}

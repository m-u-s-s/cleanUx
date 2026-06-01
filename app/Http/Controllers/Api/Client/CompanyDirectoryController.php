<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\OrganizationAccount;
use App\Models\ServiceCatalog;
use App\Services\Booking\EligibleCompaniesResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SP3 Task 7 — GET /api/client/companies
 *
 * Liste les SOCIÉTÉS prestataires éligibles pour un métier (déduit de
 * service_catalog_id) + une zone (service_zone_id), triées par note décroissante,
 * pour le browse mobile + web. L'éligibilité est entièrement déléguée à
 * EligibleCompaniesResolver (SP3 Task 3) — pas de réinvention ici.
 */
class CompanyDirectoryController extends Controller
{
    public function __invoke(Request $request, EligibleCompaniesResolver $resolver): JsonResponse
    {
        $validated = $request->validate([
            'service_zone_id' => ['required', 'integer'],
            'service_catalog_id' => ['nullable', 'integer'],
        ]);

        $zoneId = (int) $validated['service_zone_id'];

        $tradeId = null;
        if (! empty($validated['service_catalog_id'])) {
            $tradeId = ServiceCatalog::query()
                ->whereKey((int) $validated['service_catalog_id'])
                ->value('trade_id');
            $tradeId = $tradeId !== null ? (int) $tradeId : null;
        }

        $companies = $resolver->forContext($zoneId, $tradeId);

        $rows = $companies->map(fn (OrganizationAccount $org) => [
            'id' => $org->id,
            'name' => $org->name,
            'rating_avg' => $org->rating_avg !== null ? (float) $org->rating_avg : null,
            'rating_count' => (int) ($org->rating_count ?? 0),
            'providers_count' => $this->providersCount($org->id),
        ])->values();

        return response()->json(['data' => $rows]);
    }

    /**
     * Nombre de workers company_worker actifs + vérifiés rattachés à la société.
     */
    private function providersCount(int $organizationId): int
    {
        return (int) DB::table('provider_profiles')
            ->where('organization_account_id', $organizationId)
            ->where('provider_type', 'company_worker')
            ->where('status', 'active')
            ->where('verification_status', 'verified')
            ->count();
    }
}

<?php

namespace App\Http\Controllers\Api\Client;

use App\Enums\ProviderType;
use App\Http\Controllers\Controller;
use App\Models\OrganizationAccount;
use App\Models\ServiceCatalog;
use App\Services\Booking\EligibleCompaniesResolver;
use App\Services\Booking\ZoneCoverageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** SP3 Task 7 — GET /api/client/companies Liste les SOCIÉTÉS prestataires éligibles pour un métier (déduit de service_catalog_id) + une zone, triées par note décroissante, pour le browse mobile + web. */
class CompanyDirectoryController extends Controller
{
    public function __invoke(Request $request, EligibleCompaniesResolver $resolver, ZoneCoverageService $coverage): JsonResponse
    {
        $validated = $request->validate([
            'service_zone_id' => ['required_without:postal_code', 'integer'],
            'postal_code' => ['required_without:service_zone_id', 'string'],
            'city' => ['nullable', 'string'],
            'service_catalog_id' => ['nullable', 'integer'],
        ]);

        $zoneId = ! empty($validated['service_zone_id'])
            ? (int) $validated['service_zone_id']
            : $this->resolveZoneIdFromPostal($coverage, $validated['postal_code'], $validated['city'] ?? null);

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
     * Résout une service_zone_id depuis un code postal (+ ville optionnelle), via EXACTEMENT le service de couverture utilisé par la création de booking (ClientBookingController::resolveServiceZoneId).
     *
     * @throws ValidationException si le code postal ne résout aucune zone couverte.
     */
    private function resolveZoneIdFromPostal(ZoneCoverageService $coverage, string $postalCode, ?string $city): int
    {
        $postal = $coverage->resolvePostalCode($postalCode, $city);
        $zoneId = $postal ? $coverage->resolveServiceZone($postal)?->id : null;

        if (! $zoneId) {
            throw ValidationException::withMessages([
                'postal_code' => ["Ce code postal n'est couvert par aucune zone de service."],
            ]);
        }

        return (int) $zoneId;
    }

    /** Nombre de workers company_worker actifs + vérifiés rattachés à la société. */
    private function providersCount(int $organizationId): int
    {
        return (int) DB::table('provider_profiles')
            ->where('organization_account_id', $organizationId)
            ->whereIn('provider_type', ProviderType::valeursDeSociete())
            ->where('status', 'active')
            ->where('verification_status', 'verified')
            ->count();
    }
}

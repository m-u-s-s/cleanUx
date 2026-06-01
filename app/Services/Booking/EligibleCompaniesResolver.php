<?php

namespace App\Services\Booking;

use App\Enums\OrganizationType;
use App\Models\Booking;
use App\Models\OrganizationAccount;
use App\Models\User;
use Illuminate\Support\Collection;

class EligibleCompaniesResolver
{
    public function __construct(protected EmployeeAvailabilityService $availability) {}

    /**
     * @return Collection<int, OrganizationAccount>
     */
    public function forBooking(Booking $rdv): Collection
    {
        if (! $rdv->service_zone_id) {
            return collect();
        }

        return $this->forContext((int) $rdv->service_zone_id, $rdv->serviceCatalog?->trade_id);
    }

    /**
     * @return Collection<int, OrganizationAccount>
     */
    public function forContext(int $zoneId, ?int $tradeId): Collection
    {
        $workers = $this->availability
            ->sortedEligibleEmployeesForZone($zoneId, 'company')
            ->loadMissing(['providerProfile', 'trades:id'])
            ->filter(function (User $w) use ($tradeId) {
                if (! $tradeId) {
                    return true; // pas de métier connu → filtre ouvert.
                }

                // Filtre métier STRICT (pas de fallback ouvert, contrairement à
                // MatchingV2::applyTradeFilter) : un browse ne doit pas proposer une
                // société dont aucun worker n'exerce le métier demandé.
                return $w->trades->contains('id', $tradeId);
            });

        $orgIds = $workers
            ->map(fn (User $w) => $w->providerProfile?->organization_account_id)
            ->filter()
            ->unique()
            ->values();

        if ($orgIds->isEmpty()) {
            return collect();
        }

        return OrganizationAccount::query()
            ->whereIn('id', $orgIds->all())
            ->where('type', OrganizationType::PROVIDER_COMPANY->value)
            ->orderByDesc('rating_avg')
            ->orderBy('name')
            ->get();
    }

    public function isEligible(Booking $rdv, int $organizationId): bool
    {
        return $this->forBooking($rdv)->contains('id', $organizationId);
    }
}

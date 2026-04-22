<?php

namespace App\Services\Booking\Concerns;

use App\Models\OrganizationSite;
use App\Models\PostalCode;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\ZoneServiceRule;

trait ResolvesZoneCoverageLookups
{
    public function resolvePostalCode(?string $code, ?string $city = null): ?PostalCode
    {
        $code = trim((string) $code);

        if ($code === '') {
            return null;
        }

        $query = PostalCode::query()
            ->where('code', $code)
            ->where('is_active', true);

        if (filled($city)) {
            $query->where('city_name', trim((string) $city));
        }

        $postal = $query->first();

        if ($postal) {
            return $postal;
        }

        return PostalCode::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->first();
    }

    public function resolveServiceCatalog(?string $serviceIdentifier, ?ServiceZone $zone = null): ?ServiceCatalog
    {
        $serviceIdentifier = trim((string) $serviceIdentifier);

        if ($serviceIdentifier === '') {
            return null;
        }

        $normalized = mb_strtolower($serviceIdentifier);

        $catalog = ServiceCatalog::query()
            ->where('is_active', true)
            ->where(function ($query) use ($serviceIdentifier, $normalized) {
                $query
                    ->where('service_type', $serviceIdentifier)
                    ->orWhere('code', $serviceIdentifier)
                    ->orWhere('slug', $serviceIdentifier)
                    ->orWhereRaw('LOWER(service_type) = ?', [$normalized])
                    ->orWhereRaw('LOWER(code) = ?', [$normalized])
                    ->orWhereRaw('LOWER(slug) = ?', [$normalized]);
            })
            ->first();

        if ($catalog) {
            return $catalog;
        }

        if ($zone) {
            $rules = ZoneServiceRule::query()
                ->with('serviceCatalog')
                ->where('service_zone_id', $zone->id)
                ->where('is_enabled', true)
                ->get();

            if ($rules->count() === 1 && $rules->first()?->serviceCatalog?->is_active) {
                return $rules->first()->serviceCatalog;
            }
        }

        return null;
    }

    public function resolveServiceZone(?PostalCode $postalCode, ?OrganizationSite $selectedSite = null, bool $bookableOnly = false): ?ServiceZone
    {
        return $this->resolveServiceZoneWithSource($postalCode, $selectedSite, $bookableOnly)['zone'];
    }

    public function resolveServiceZoneWithSource(?PostalCode $postalCode, ?OrganizationSite $selectedSite = null, bool $bookableOnly = false): array
    {
        if (! $postalCode && ! $selectedSite?->service_zone_id) {
            return ['zone' => null, 'source' => null];
        }

        $applyConstraints = function ($query) use ($bookableOnly) {
            $query
                ->when(
                    $bookableOnly,
                    fn ($q) => $q->where('status', 'active')->where('is_bookable', true),
                    fn ($q) => $q->whereIn('status', ['active', 'paused'])
                )
                ->orderBy('priority');
        };

        if ($selectedSite?->service_zone_id) {
            $zone = ServiceZone::query()->whereKey($selectedSite->service_zone_id);
            $applyConstraints($zone);
            $zone = $zone->first();

            if ($zone) {
                return ['zone' => $zone, 'source' => 'organization_site'];
            }
        }

        if ($postalCode) {
            $zone = ServiceZone::query()
                ->whereHas('postalCodes', fn ($query) => $query->where('postal_codes.id', $postalCode->id));
            $applyConstraints($zone);
            $zone = $zone->first();

            if ($zone) {
                return ['zone' => $zone, 'source' => 'postal_code'];
            }
        }

        if ($postalCode?->province_id) {
            $zone = ServiceZone::query()->where('province_id', $postalCode->province_id);
            $applyConstraints($zone);
            $zone = $zone->first();

            if ($zone) {
                return ['zone' => $zone, 'source' => 'province_fallback'];
            }
        }

        if ($postalCode?->region_id) {
            $zone = ServiceZone::query()->where('region_id', $postalCode->region_id);
            $applyConstraints($zone);
            $zone = $zone->first();

            if ($zone) {
                return ['zone' => $zone, 'source' => 'region_fallback'];
            }
        }

        $zone = ServiceZone::query()->where('coverage_type', 'national');
        $applyConstraints($zone);

        return ['zone' => $zone->first(), 'source' => 'national_fallback'];
    }

    public function resolveZoneServiceRule(?ServiceZone $zone, ?ServiceCatalog $catalog): ?ZoneServiceRule
    {
        if (! $zone || ! $catalog) {
            return null;
        }

        return ZoneServiceRule::query()
            ->where('service_zone_id', $zone->id)
            ->where('service_catalog_id', $catalog->id)
            ->where('is_enabled', true)
            ->first();
    }
}

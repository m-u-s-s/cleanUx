<?php

namespace App\Services\Booking;

use App\Data\ZoneCoverageResult;
use App\Models\OrganizationSite;
use App\Models\PostalCode;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\ZoneServiceRule;
use Illuminate\Support\Str;

class ZoneCoverageService
{
    public function resolvePostalCode(?string $code, ?string $city = null): ?PostalCode
    {
        $code = trim((string) $code);

        if ($code === '') {
            return null;
        }

        $candidates = PostalCode::query()
            ->with('commune')
            ->where('code', $code)
            ->where('is_active', true)
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $normalizedCity = $this->normalizeCityName($city);

        if ($normalizedCity === null) {
            return $this->preferPrimaryPostalCodeCandidate($candidates);
        }

        $matchingAliases = $this->cityAliasesForLookup($normalizedCity);

        $exact = $candidates->first(function (PostalCode $postalCode) use ($matchingAliases) {
            return in_array($this->normalizeCityName($postalCode->city_name), $matchingAliases, true);
        });

        if ($exact) {
            return $exact;
        }

        $communeMatch = $candidates->first(function (PostalCode $postalCode) use ($matchingAliases) {
            return in_array($this->normalizeCityName($postalCode->commune?->name), $matchingAliases, true);
        });

        return $communeMatch ?: $this->preferPrimaryPostalCodeCandidate($candidates);
    }

    protected function preferPrimaryPostalCodeCandidate($candidates): ?PostalCode
    {
        $primary = $candidates->first(function (PostalCode $postalCode) {
            return $this->normalizeCityName($postalCode->city_name) === $this->normalizeCityName($postalCode->commune?->name);
        });

        if ($primary) {
            return $primary;
        }

        return $candidates
            ->sortBy(function (PostalCode $postalCode) {
                $city = $this->normalizeCityName($postalCode->city_name) ?? '';
                $commune = $this->normalizeCityName($postalCode->commune?->name) ?? '';

                return [
                    $city !== $commune,
                    Str::length($postalCode->city_name),
                    $postalCode->city_name,
                ];
            })
            ->first();
    }

    protected function normalizeCityName(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $value = Str::ascii($value);
        $value = Str::lower($value);
        $value = str_replace(["'", '-', '/'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value ?? '');

        return trim((string) $value);
    }

    protected function cityAliasesForLookup(string $normalizedCity): array
    {
        $aliases = [
            'bruxelles' => ['bruxelles', 'brussel', 'brussels'],
            'brussel' => ['bruxelles', 'brussel', 'brussels'],
            'brussels' => ['bruxelles', 'brussel', 'brussels'],
            'ixelles' => ['ixelles', 'elsene'],
            'elsene' => ['ixelles', 'elsene'],
            'uccle' => ['uccle', 'ukkel'],
            'ukkel' => ['uccle', 'ukkel'],
            'schaerbeek' => ['schaerbeek', 'schaarbeek'],
            'schaarbeek' => ['schaerbeek', 'schaarbeek'],
            'saint gilles' => ['saint gilles', 'sint gillis'],
            'sint gillis' => ['saint gilles', 'sint gillis'],
            'molenbeek saint jean' => ['molenbeek saint jean', 'sint jans molenbeek'],
            'sint jans molenbeek' => ['molenbeek saint jean', 'sint jans molenbeek'],
            'berchem sainte agathe' => ['berchem sainte agathe', 'sint agatha berchem'],
            'sint agatha berchem' => ['berchem sainte agathe', 'sint agatha berchem'],
            'woluwe saint pierre' => ['woluwe saint pierre', 'sint pieters woluwe'],
            'sint pieters woluwe' => ['woluwe saint pierre', 'sint pieters woluwe'],
            'auderghem' => ['auderghem', 'oudergem'],
            'oudergem' => ['auderghem', 'oudergem'],
            'woluwe saint lambert' => ['woluwe saint lambert', 'sint lambrechts woluwe'],
            'sint lambrechts woluwe' => ['woluwe saint lambert', 'sint lambrechts woluwe'],
            'saint josse ten noode' => ['saint josse ten noode', 'sint joost ten node'],
            'sint joost ten node' => ['saint josse ten noode', 'sint joost ten node'],
            'anvers' => ['anvers', 'antwerpen', 'antwerp'],
            'antwerpen' => ['anvers', 'antwerpen', 'antwerp'],
            'antwerp' => ['anvers', 'antwerpen', 'antwerp'],
            'malines' => ['malines', 'mechelen'],
            'mechelen' => ['malines', 'mechelen'],
            'louvain' => ['louvain', 'leuven'],
            'leuven' => ['louvain', 'leuven'],
            'gand' => ['gand', 'gent', 'ghent'],
            'gent' => ['gand', 'gent', 'ghent'],
            'ghent' => ['gand', 'gent', 'ghent'],
            'bruges' => ['bruges', 'brugge'],
            'brugge' => ['bruges', 'brugge'],
            'courtrai' => ['courtrai', 'kortrijk'],
            'kortrijk' => ['courtrai', 'kortrijk'],
            'ostende' => ['ostende', 'oostende'],
            'oostende' => ['ostende', 'oostende'],
            'liege' => ['liege', 'luik'],
            'luik' => ['liege', 'luik'],
            'mons' => ['mons', 'bergen'],
            'bergen' => ['mons', 'bergen'],
            'namur' => ['namur', 'namen'],
            'namen' => ['namur', 'namen'],
            'arlon' => ['arlon', 'aarlen'],
            'aarlen' => ['arlon', 'aarlen'],
            'wavre' => ['wavre', 'waver'],
            'waver' => ['wavre', 'waver'],
            'nivelles' => ['nivelles', 'nijvel'],
            'nijvel' => ['nivelles', 'nijvel'],
            'tournai' => ['tournai', 'doornik'],
            'doornik' => ['tournai', 'doornik'],
            'saint nicolas' => ['saint nicolas', 'sint niklaas'],
            'sint niklaas' => ['saint nicolas', 'sint niklaas'],
            'la louviere' => ['la louviere'],
        ];

        return $aliases[$normalizedCity] ?? [$normalizedCity];
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

    public function resolveCoverage(?string $codePostal, ?string $city, ?string $serviceIdentifier, ?OrganizationSite $selectedSite = null): ZoneCoverageResult
    {
        $postalCode = $this->resolvePostalCode($codePostal, $city);

        if (! $postalCode && $selectedSite?->postal_code_id) {
            $selectedSite->loadMissing('postalCodeReference');
            $postalCode = $selectedSite->postalCodeReference;
        }

        $zoneResolution = $this->resolveServiceZoneWithSource($postalCode, $selectedSite, false);
        $zone = $zoneResolution['zone'];
        $resolutionSource = $zoneResolution['source'];
        $serviceCatalog = $this->resolveServiceCatalog($serviceIdentifier, $zone);
        $rule = $this->resolveZoneServiceRule($zone, $serviceCatalog);

        if (! $postalCode) {
            return new ZoneCoverageResult(
                postalCode: null,
                zone: null,
                serviceCatalog: $serviceCatalog,
                zoneServiceRule: null,
                status: 'invalid_postal_code',
                message: 'Code postal ou ville non reconnu.',
                resolutionSource: $resolutionSource,
            );
        }

        if (! $zone) {
            return new ZoneCoverageResult(
                postalCode: $postalCode,
                zone: null,
                serviceCatalog: $serviceCatalog,
                zoneServiceRule: null,
                status: 'unsupported',
                message: 'Cette zone n’est pas encore couverte.',
                resolutionSource: $resolutionSource,
            );
        }

        if ($zone->status === 'paused') {
            return new ZoneCoverageResult(
                postalCode: $postalCode,
                zone: $zone,
                serviceCatalog: $serviceCatalog,
                zoneServiceRule: $rule,
                status: 'paused',
                message: 'Cette zone est temporairement en pause.',
                resolutionSource: $resolutionSource,
            );
        }

        if (! $zone->is_visible && ! $zone->is_bookable) {
            return new ZoneCoverageResult(
                postalCode: $postalCode,
                zone: $zone,
                serviceCatalog: $serviceCatalog,
                zoneServiceRule: $rule,
                status: 'hidden',
                message: 'Cette zone est actuellement indisponible à la réservation.',
                resolutionSource: $resolutionSource,
            );
        }

        if (! $zone->is_bookable) {
            return new ZoneCoverageResult(
                postalCode: $postalCode,
                zone: $zone,
                serviceCatalog: $serviceCatalog,
                zoneServiceRule: $rule,
                status: 'non_bookable',
                message: 'Zone visible mais réservable uniquement après validation manuelle.',
                resolutionSource: $resolutionSource,
            );
        }

        if ($serviceIdentifier && ! $serviceCatalog) {
            return new ZoneCoverageResult(
                postalCode: $postalCode,
                zone: $zone,
                serviceCatalog: null,
                zoneServiceRule: null,
                status: 'service_unavailable',
                message: 'Service introuvable ou indisponible dans cette zone.',
                resolutionSource: $resolutionSource,
            );
        }

        if ($serviceIdentifier && ! $rule) {
            return new ZoneCoverageResult(
                postalCode: $postalCode,
                zone: $zone,
                serviceCatalog: $serviceCatalog,
                zoneServiceRule: null,
                status: 'service_unavailable',
                message: 'Ce service n’est pas disponible dans votre zone.',
                resolutionSource: $resolutionSource,
            );
        }

        $requiresManualValidation = (bool) ($rule?->requires_manual_validation
            || data_get($zone->metadata, 'requires_manual_validation', false)
            || $serviceCatalog?->requires_manual_validation);

        if ($requiresManualValidation) {
            return new ZoneCoverageResult(
                postalCode: $postalCode,
                zone: $zone,
                serviceCatalog: $serviceCatalog,
                zoneServiceRule: $rule,
                status: 'manual_validation',
                message: 'Zone couverte : ' . $zone->name . ' — validation manuelle requise pour ce service.',
                resolutionSource: $resolutionSource,
            );
        }

        return new ZoneCoverageResult(
            postalCode: $postalCode,
            zone: $zone,
            serviceCatalog: $serviceCatalog,
            zoneServiceRule: $rule,
            status: 'covered',
            message: 'Zone couverte : ' . $zone->name,
            resolutionSource: $resolutionSource,
        );
    }
}

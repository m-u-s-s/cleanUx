<?php

namespace App\Services\Booking\Concerns;

use App\Data\ZoneCoverageResult;
use App\Models\OrganizationSite;

trait EvaluatesZoneCoverageStates
{
    public function resolveCoverage(?string $codePostal, ?string $city, ?string $serviceType, ?OrganizationSite $selectedSite = null): ZoneCoverageResult
    {
        $postalCode = $this->resolvePostalCode($codePostal, $city);

        if (! $postalCode && $selectedSite?->postal_code_id) {
            $selectedSite->loadMissing('postalCodeReference');
            $postalCode = $selectedSite->postalCodeReference;
        }

        $zoneResolution = $this->resolveServiceZoneWithSource($postalCode, $selectedSite, false);
        $zone = $zoneResolution['zone'];
        $resolutionSource = $zoneResolution['source'];
        $serviceCatalog = $this->resolveServiceCatalog($serviceType, $zone);
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

        if ($serviceType && ! $serviceCatalog) {
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

        if ($serviceType && ! $rule) {
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

        return new ZoneCoverageResult(
            postalCode: $postalCode,
            zone: $zone,
            serviceCatalog: $serviceCatalog,
            zoneServiceRule: $rule,
            status: $rule && ($rule->requires_manual_validation || ! $zone->is_bookable) ? 'manual_validation' : 'covered',
            message: $rule && $rule->requires_manual_validation
                ? 'Votre demande nécessite une validation manuelle avant confirmation.'
                : 'Zone et service disponibles.',
            resolutionSource: $resolutionSource,
        );
    }
}

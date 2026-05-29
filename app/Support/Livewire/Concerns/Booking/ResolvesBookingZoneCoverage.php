<?php

namespace App\Support\Livewire\Concerns\Booking;

use App\Data\ZoneCoverageResult;
use App\Models\OrganizationSite;
use App\Models\PostalCode;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\TradeZoneSetting;
use App\Models\ZoneServiceRule;
use App\Services\International\CountryMarketResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

trait ResolvesBookingZoneCoverage
{
    public ?int $resolvedServiceZoneId = null;

    protected function countryMarketResolver(): CountryMarketResolver
    {
        return app(CountryMarketResolver::class);
    }

    protected function currentCountryMarketContext(): array
    {
        return $this->countryMarketResolver()->resolveForBooking(
            Auth::user(),
            $this->currentPostalCode(),
            $this->currentServiceZone(),
            $this->selectedOrganizationSite(),
            $this->currentServiceCatalog(),
        );
    }

    protected function currentServiceCatalog(): ?ServiceCatalog
    {
        if (! $this->selected_service_identifier) {
            $this->resolvedServiceCatalogId = null;

            return null;
        }

        $catalog = $this->zoneCoverageService()->resolveServiceCatalog(
            $this->selected_service_identifier,
            $this->currentServiceZone(),
        );

        $this->resolvedServiceCatalogId = $catalog?->id;

        return $catalog;
    }

    protected function currentPostalCode(): ?PostalCode
    {
        return $this->zoneCoverageService()->resolvePostalCode($this->postal_code_input, $this->ville);
    }

    protected function resolveServiceZoneCandidate(bool $bookableOnly = false): ?ServiceZone
    {
        return $this->zoneCoverageService()->resolveServiceZone(
            $this->currentPostalCode(),
            $this->selectedOrganizationSite(),
            $bookableOnly,
        );
    }

    protected function currentServiceZone(): ?ServiceZone
    {
        return $this->resolveServiceZoneCandidate(false);
    }

    protected function currentBookableServiceZone(): ?ServiceZone
    {
        return $this->resolveServiceZoneCandidate(true);
    }

    protected function currentZoneServiceRule(): ?ZoneServiceRule
    {
        return $this->zoneCoverageService()->resolveZoneServiceRule(
            $this->currentServiceZone(),
            $this->currentServiceCatalog(),
        );
    }

    protected function currentCoverageResolution(): ZoneCoverageResult
    {
        return $this->zoneCoverageService()->resolveCoverage(
            $this->postal_code_input,
            $this->ville,
            $this->selected_service_identifier,
            $this->selectedOrganizationSite(),
        );
    }

    protected function resolveCoverageContext(): void
    {
        $resolution = $this->currentCoverageResolution();

        $this->resolvedPostalCodeId = $resolution->postalCode?->id;
        $this->resolvedServiceZoneId = $resolution->zone?->id;
        $this->resolvedServiceCatalogId = $resolution->serviceCatalog?->id;
        $this->coverageStatus = $resolution->status;
        $this->coverageMessage = $resolution->message;
    }

    protected function ensureCoverageIsBookable(): bool
    {
        $this->resolveCoverageContext();
        $resolution = $this->currentCoverageResolution();

        if (! $resolution->postalCode) {
            $this->addError('postal_code_input', 'Code postal ou ville non reconnu.');

            return false;
        }

        if (! $resolution->zone) {
            $this->addError('postal_code_input', 'Cette zone n’est pas encore couverte.');

            return false;
        }

        if ($resolution->zone->status !== 'active') {
            $this->addError('postal_code_input', 'Cette zone est temporairement indisponible.');

            return false;
        }

        if (! $resolution->zone->is_bookable) {
            $this->addError('postal_code_input', 'Cette zone n’est pas réservable en ligne pour le moment.');

            return false;
        }

        if (! $resolution->serviceCatalog) {
            $this->addError('selected_service_identifier', 'Service introuvable.');

            return false;
        }

        if ($resolution->serviceCatalog->is_entreprise && ! $this->isEntrepriseCustomer()) {
            $this->addError('selected_service_identifier', 'Ce service est réservé aux comptes entreprise.');

            return false;
        }

        if (! $resolution->zoneServiceRule) {
            $this->addError('selected_service_identifier', 'Ce service n’est pas disponible dans votre zone.');

            return false;
        }

        if ($resolution->serviceCatalog->trade_id) {
            $disabled = $this->disabledTradeIdsForZone($resolution->zone->id);
            if ($disabled->contains($resolution->serviceCatalog->trade_id)) {
                $this->addError('selected_service_identifier', 'Ce métier est temporairement désactivé dans votre zone.');

                return false;
            }
        }

        return true;
    }

    protected function normalizedBookingLocationData(PostalCode $postal, ?OrganizationSite $organizationSite): array
    {
        if ($organizationSite) {
            return [
                'adresse' => $organizationSite->address_line_1 ?: $this->adresse,
                'ville' => $organizationSite->city ?: $postal->city_name,
                'code_postal' => $organizationSite->postal_code ?: $postal->code,
            ];
        }

        return [
            'adresse' => $this->adresse,
            'ville' => $postal->city_name,
            'code_postal' => $postal->code,
        ];
    }

    protected function bookingMotifFor(ServiceCatalog $catalog, PostalCode $postal, ?OrganizationSite $organizationSite): string
    {
        $locationLabel = $organizationSite?->name ?: $postal->city_name;

        return trim($catalog->name.' · '.$locationLabel);
    }

    /**
     * IDs des métiers explicitement désactivés dans la zone (TradeZoneSetting.is_active = false).
     * Absence de ligne = métier implicitement actif (back-compat).
     */
    protected function disabledTradeIdsForZone(int $serviceZoneId): Collection
    {
        return TradeZoneSetting::query()
            ->where('service_zone_id', $serviceZoneId)
            ->where('is_active', false)
            ->pluck('trade_id');
    }
}

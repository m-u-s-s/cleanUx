<?php

namespace App\Services\Finance;

use App\Models\Booking;
use App\Services\International\CountryMarketResolver;

class FinanceDocumentCalculator
{
    public function __construct(
        protected CountryMarketResolver $countryMarketResolver,
    ) {}

    public function amountBreakdownFor(Booking $rdv): array
    {
        $pricing = (array) ($rdv->pricing_snapshot ?? []);
        $market = $this->countryMarketResolver->resolveForRendezVous($rdv);

        $basePrice = round((float) (
            data_get($pricing, 'devis_estime')
            ?? $rdv->devis_estime
            ?? data_get($pricing, 'base_price_override')
            ?? data_get($pricing, 'base_price')
            ?? $rdv->serviceCatalog?->base_price
            ?? 0
        ), 2);

        $travelSurcharge = round((float) (
            data_get($pricing, 'travel_surcharge')
            ?? data_get($pricing, 'zone.travel_surcharge')
            ?? data_get($pricing, 'zone_snapshot.travel_surcharge')
            ?? $rdv->serviceZone?->travel_surcharge
            ?? 0
        ), 2);

        $discountRate = round((float) (
            data_get($pricing, 'corporate_context.negotiated_discount_rate')
            ?? data_get($rdv->organizationAccount?->metadata, 'finance.negotiated_discount_rate')
            ?? data_get($rdv->organizationAccount?->metadata, 'contract.negotiated_discount_rate')
            ?? 0
        ), 2);

        $discountAmount = round((($basePrice + $travelSurcharge) * max($discountRate, 0)) / 100, 2);
        $subtotal = round(max(($basePrice + $travelSurcharge) - $discountAmount, 0), 2);

        $taxRate = $this->countryMarketResolver->effectiveTaxRate($market, $rdv);
        $taxAmount = round($subtotal * ($taxRate / 100), 2);
        $totalAmount = round($subtotal + $taxAmount, 2);

        $durationMinutes = (int) (
            $rdv->duree_reelle
            ?? $rdv->duree
            ?? data_get($pricing, 'duration_minutes')
            ?? $rdv->serviceCatalog?->default_duration_minutes
            ?? 0
        );

        $hourlyCost = round((float) (
            data_get($rdv->employe?->metadata, 'finance.hourly_cost')
            ?? data_get($rdv->organizationAccount?->metadata, 'finance.default_employee_hourly_cost')
            ?? 18
        ), 2);

        $fixedMissionCost = round((float) (
            data_get($rdv->organizationAccount?->metadata, 'finance.fixed_mission_cost')
            ?? data_get($pricing, 'fixed_mission_cost')
            ?? 0
        ), 2);

        $estimatedInternalCost = round((($durationMinutes / 60) * $hourlyCost) + $fixedMissionCost, 2);
        $estimatedMarginAmount = round($subtotal - $estimatedInternalCost, 2);
        $estimatedMarginRate = $subtotal > 0 ? round(($estimatedMarginAmount / $subtotal) * 100, 1) : 0.0;

        $paymentTermsDays = $this->countryMarketResolver->paymentTermsDays($market, $rdv);
        $quoteValidityDays = $this->countryMarketResolver->quoteValidityDays($market, $rdv);

        return [
            'base_price' => $basePrice,
            'travel_surcharge' => $travelSurcharge,
            'discount_rate' => $discountRate,
            'discount_amount' => $discountAmount,
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'duration_minutes' => $durationMinutes,
            'employee_hourly_cost' => $hourlyCost,
            'fixed_mission_cost' => $fixedMissionCost,
            'estimated_internal_cost' => $estimatedInternalCost,
            'estimated_margin_amount' => $estimatedMarginAmount,
            'estimated_margin_rate' => $estimatedMarginRate,
            'payment_terms_days' => $paymentTermsDays,
            'quote_validity_days' => $quoteValidityDays,
            'currency' => $this->countryMarketResolver->effectiveCurrency($market),
            'document_formatting' => $this->countryMarketResolver->formatting($market),
        ];
    }

    public function snapshotFor(Booking $rdv, array $market = []): array
    {
        $serviceName = $rdv->service_display_name
            ?: $rdv->serviceCatalog?->name
            ?: data_get($rdv->pricing_snapshot, 'service_name')
            ?: data_get($rdv->pricing_snapshot, 'service.name')
            ?: data_get($rdv->zone_snapshot, 'service_name')
            ?: $rdv->motif;

        $serviceIdentifier = $rdv->service_identifier_display
            ?: data_get($rdv->pricing_snapshot, 'service_identifier')
            ?: data_get($rdv->pricing_snapshot, 'service.service_identifier')
            ?: data_get($rdv->zone_snapshot, 'service_identifier')
            ?: $rdv->serviceCatalog?->code
            ?: $rdv->serviceCatalog?->slug;

        $postalCode = $rdv->postalCode?->code
            ?: data_get($rdv->pricing_snapshot, 'postal_code')
            ?: data_get($rdv->zone_snapshot, 'postal_code')
            ?: $rdv->code_postal;

        $city = $rdv->postalCode?->city_name
            ?: $rdv->postalCode?->commune?->name
            ?: data_get($rdv->pricing_snapshot, 'ville')
            ?: data_get($rdv->zone_snapshot, 'ville')
            ?: $rdv->ville;

        $locationDisplay = collect([$rdv->adresse, $postalCode, $city])->filter(fn ($value) => filled($value))->implode(', ');

        return [
            'booking_reference' => $rdv->booking_reference,
            'service_name' => $serviceName,
            'service_identifier' => $serviceIdentifier,
            'date' => optional($rdv->date)->toDateString(),
            'heure' => $rdv->heure,
            'adresse' => $rdv->adresse,
            'postal_code' => $postalCode,
            'ville' => $city,
            'location_display' => $locationDisplay,
            'zone_name' => $rdv->serviceZone?->name,
            'organization_name' => $rdv->organizationAccount?->name,
            'site_name' => $rdv->organizationSite?->name,
            'client_name' => $rdv->client?->name,
            'country_iso' => data_get($market['country'] ?? null, 'iso_code') ?: data_get($rdv->zone_snapshot, 'country_iso'),
            'country_name' => data_get($market['country'] ?? null, 'name') ?: data_get($rdv->zone_snapshot, 'country_name'),
            'market_stage' => $this->countryMarketResolver->marketStage($market),
        ];
    }

    public function nextQuoteNumber(Booking $rdv, array $market = []): string
    {
        $prefix = (string) (data_get($market['billing_profile'] ?? null, 'quote_prefix') ?: 'DEV');

        return $prefix.'-'.now()->format('Y').'-'.str_pad((string) ($rdv->id ?: 0), 6, '0', STR_PAD_LEFT);
    }

    public function nextInvoiceNumber(Booking $rdv, array $market = []): string
    {
        $prefix = (string) (data_get($market['billing_profile'] ?? null, 'invoice_prefix') ?: 'FAC');

        return $prefix.'-'.now()->format('Y').'-'.str_pad((string) ($rdv->id ?: 0), 6, '0', STR_PAD_LEFT);
    }

    public function quoteStatusFor(Booking $rdv): string
    {
        return match ($rdv->status) {
            'annule', 'refuse' => 'cancelled',
            'confirme', 'en_route', 'sur_place', 'termine' => 'accepted',
            default => 'draft',
        };
    }

    public function invoiceStatusFor(Booking $rdv): string
    {
        return match ($rdv->status) {
            'termine' => 'issued',
            'en_route', 'sur_place' => 'sent',
            default => 'draft',
        };
    }
}

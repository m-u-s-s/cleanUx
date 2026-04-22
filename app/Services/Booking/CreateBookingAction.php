<?php

namespace App\Services\Booking;

use App\Data\ZoneCoverageResult;
use App\Models\OrganizationSite;
use App\Models\PostalCode;
use App\Models\RendezVous;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\User;
use App\Models\ZoneServiceRule;
use App\Services\International\CountryMarketResolver;
use App\Support\ActivityLogger;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class CreateBookingAction
{
    public function __construct(
        protected BookingSnapshotFactory $snapshotFactory,
        protected CountryMarketResolver $countryMarketResolver,
    ) {}

    public function execute(
        User $client,
        PostalCode $postal,
        ServiceZone $zone,
        ServiceCatalog $catalog,
        ZoneServiceRule $rule,
        User $assignedEmployee,
        array $data,
        ?OrganizationSite $organizationSite = null,
        ?ZoneCoverageResult $resolution = null,
    ): RendezVous {
        $resolution ??= new ZoneCoverageResult(
            postalCode: $postal,
            zone: $zone,
            serviceCatalog: $catalog,
            zoneServiceRule: $rule,
            status: Arr::get($data, 'entreprise_approval_required', false) ? 'manual_validation' : 'covered',
            message: 'Zone couverte : ' . $zone->name,
            resolutionSource: Arr::get($data, 'resolution_source'),
        );

        $countryMarket = $this->countryMarketResolver->resolveForBooking($client, $postal, $zone, $organizationSite, $catalog);

        if (! $this->countryMarketResolver->bookingEnabled($countryMarket)) {
            throw ValidationException::withMessages([
                'postal_code_input' => 'La réservation n’est pas encore active pour ce marché.',
            ]);
        }

        if (! $this->countryMarketResolver->serviceEnabled($countryMarket)) {
            throw ValidationException::withMessages([
                'selected_service_identifier' => 'Ce service n’est pas encore disponible dans ce pays.',
            ]);
        }

        $manualValidationRequired = $resolution->requiresManualValidation()
            || (bool) ($rule->requires_manual_validation
                || data_get($zone->metadata, 'requires_manual_validation', false)
                || $catalog->requires_manual_validation
                || Arr::get($data, 'entreprise_approval_required', false)
                || $this->countryMarketResolver->requiresManualValidation($countryMarket)
                || $this->countryMarketResolver->requiresQuote($countryMarket));

        $status = Arr::get($data, 'status', 'en_attente');
        if ($manualValidationRequired && $status === 'confirme') {
            $status = 'en_attente';
        }

        $serviceIdentifier = (string) (Arr::get($data, 'service_identifier') ?: $catalog->code ?: $catalog->slug);
        if ($serviceIdentifier === '') {
            throw new \LogicException('Impossible de créer un rendez-vous sans service_identifier.');
        }

        $zoneSnapshot = array_merge(
            $this->snapshotFactory->makeZoneSnapshot($postal, $zone, $organizationSite, $resolution),
            [
                'country_id' => data_get($countryMarket['country'] ?? null, 'id'),
                'country_iso' => data_get($countryMarket['country'] ?? null, 'iso_code'),
                'country_iso3' => data_get($countryMarket['country'] ?? null, 'iso3_code'),
                'country_name' => data_get($countryMarket['country'] ?? null, 'name'),
                'market_stage' => $this->countryMarketResolver->marketStage($countryMarket),
            ]
        );

        $pricingSnapshot = $this->snapshotFactory->makePricingSnapshot($catalog, $zone, $rule, $resolution, $data);
        $countryMultiplier = $this->countryMarketResolver->countryPriceMultiplier($countryMarket);
        $adjustedEstimate = round((float) (Arr::get($data, 'devis_estime', data_get($pricingSnapshot, 'devis_estime', 0))) * $countryMultiplier, 2);

        $pricingSnapshot = array_merge($pricingSnapshot, [
            'devis_estime' => $adjustedEstimate,
            'currency' => $this->countryMarketResolver->effectiveCurrency($countryMarket),
            'tax_rate' => $this->countryMarketResolver->effectiveTaxRate($countryMarket),
            'quote_required' => $this->countryMarketResolver->requiresQuote($countryMarket),
            'country_market' => [
                'country_id' => data_get($countryMarket['country'] ?? null, 'id'),
                'country_iso' => data_get($countryMarket['country'] ?? null, 'iso_code'),
                'market_stage' => $this->countryMarketResolver->marketStage($countryMarket),
                'service_price_multiplier' => $countryMultiplier,
                'minimum_notice_hours' => $this->countryMarketResolver->minimumNoticeHours($countryMarket),
                'requires_manual_validation' => $this->countryMarketResolver->requiresManualValidation($countryMarket),
                'requires_quote' => $this->countryMarketResolver->requiresQuote($countryMarket),
                'document_formatting' => $this->countryMarketResolver->formatting($countryMarket),
                'default_team_id' => data_get($countryMarket['service_rule'] ?? null, 'default_team_id'),
                'default_partner_id' => data_get($countryMarket['service_rule'] ?? null, 'default_partner_id'),
            ],
        ]);

        $rendezVous = RendezVous::create([
            'client_id' => $client->id,
            'employe_id' => $assignedEmployee->id,
            'organization_account_id' => $client->organization_account_id,
            'organization_site_id' => $organizationSite?->id,
            'service_catalog_id' => $catalog->id,
            'service_zone_id' => $zone->id,
            'postal_code_id' => $postal->id,
            'booking_channel' => Arr::get($data, 'booking_channel', 'web'),
            'booking_reference' => Arr::get($data, 'booking_reference'),
            'zone_snapshot' => $zoneSnapshot,
            'pricing_snapshot' => $pricingSnapshot,
            'date' => Arr::get($data, 'date'),
            'heure' => Arr::get($data, 'heure'),
            'motif' => Arr::get($data, 'motif', $catalog->name),
            'type_lieu' => Arr::get($data, 'type_lieu'),
            'frequence' => Arr::get($data, 'frequence'),
            'surface' => Arr::get($data, 'surface'),
            'adresse' => Arr::get($data, 'adresse'),
            'ville' => Arr::get($data, 'ville', $postal->city_name),
            'code_postal' => Arr::get($data, 'code_postal', $postal->code),
            'telephone_client' => Arr::get($data, 'telephone_client'),
            'priorite' => Arr::get($data, 'priorite', 'normale'),
            'commentaire_client' => Arr::get($data, 'commentaire_client'),
            'options_prestation' => Arr::get($data, 'options_prestation', []),
            'zones_specifiques' => Arr::get($data, 'zones_specifiques', []),
            'materiel_specifique' => Arr::get($data, 'materiel_specifique', []),
            'presence_animaux' => (bool) Arr::get($data, 'presence_animaux', false),
            'acces_parking' => (bool) Arr::get($data, 'acces_parking', false),
            'materiel_fournit' => (bool) Arr::get($data, 'materiel_fournit', false),
            'is_recurrent' => (bool) Arr::get($data, 'is_recurrent', false),
            'recurrence_rule' => Arr::get($data, 'recurrence_rule'),
            'recurring_series_id' => Arr::get($data, 'recurring_series_id'),
            'recurrence_frequency' => Arr::get($data, 'recurrence_frequency'),
            'recurrence_interval' => Arr::get($data, 'recurrence_interval'),
            'recurrence_until' => Arr::get($data, 'recurrence_until'),
            'recurrence_count' => Arr::get($data, 'recurrence_count'),
            'recurrence_days' => Arr::get($data, 'recurrence_days'),
            'is_series_master' => (bool) Arr::get($data, 'is_series_master', false),
            'series_position' => Arr::get($data, 'series_position'),
            'series_status' => Arr::get($data, 'series_status'),
            'is_favorite_slot' => (bool) Arr::get($data, 'is_favorite_slot', false),
            'photos_reference' => Arr::get($data, 'photos_reference', []),
            'duree_estimee' => Arr::get($data, 'duree_estimee'),
            'devis_estime' => $adjustedEstimate,
            'status' => $status,
        ]);

        ActivityLogger::log('booking.created', $rendezVous, [
            'booking_reference' => $rendezVous->booking_reference,
            'client_id' => $client->id,
            'employee_id' => $assignedEmployee->id,
            'service_zone_id' => $zone->id,
            'service_catalog_id' => $catalog->id,
            'postal_code_id' => $postal->id,
            'manual_validation_required' => $manualValidationRequired,
            'coverage_resolution_source' => $resolution->resolutionSource,
            'country_iso' => data_get($countryMarket['country'] ?? null, 'iso_code'),
            'market_stage' => $this->countryMarketResolver->marketStage($countryMarket),
        ]);

        return $rendezVous;
    }
}

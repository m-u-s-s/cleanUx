<?php

namespace App\Services\Booking;

use App\Data\ZoneCoverageResult;
use App\Models\OrganizationSite;
use App\Models\PostalCode;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\ZoneServiceRule;
use Illuminate\Support\Arr;

class BookingSnapshotFactory
{
    public function makeZoneSnapshot(
        PostalCode $postal,
        ServiceZone $zone,
        ?OrganizationSite $organizationSite,
        ZoneCoverageResult $resolution,
    ): array {
        return [
            'resolution' => [
                'status' => $resolution->status,
                'message' => $resolution->message,
                'source' => $resolution->resolutionSource,
            ],
            'zone' => [
                'id' => $zone->id,
                'code' => $zone->code,
                'name' => $zone->name,
                'slug' => $zone->slug,
                'coverage_type' => $zone->coverage_type,
                'status' => $zone->status,
                'is_bookable' => (bool) $zone->is_bookable,
                'is_visible' => (bool) $zone->is_visible,
                'priority' => (int) $zone->priority,
                'minimum_notice_hours' => (int) ($zone->minimum_notice_hours ?? 0),
                'maximum_daily_jobs' => $zone->maximum_daily_jobs !== null ? (int) $zone->maximum_daily_jobs : null,
                'travel_surcharge' => (float) ($zone->travel_surcharge ?? 0),
                'time_buffer_minutes' => (int) ($zone->time_buffer_minutes ?? 0),
            ],
            'postal_code' => [
                'id' => $postal->id,
                'code' => $postal->code,
                'city_name' => $postal->city_name,
                'commune_id' => $postal->commune_id,
                'province_id' => $postal->province_id,
                'region_id' => $postal->region_id,
                'country_id' => $postal->country_id,
            ],
            'organization_site' => $organizationSite ? [
                'id' => $organizationSite->id,
                'name' => $organizationSite->name,
                'site_code' => $organizationSite->site_code,
                'service_zone_id' => $organizationSite->service_zone_id,
                'postal_code_id' => $organizationSite->postal_code_id,
            ] : null,

            // legacy compatibility
            'zone_id' => $zone->id,
            'zone_name' => $zone->name,
            'coverage_type' => $zone->coverage_type,
            'postal_code_id' => $postal->id,
            'postal_code_value' => $postal->code,
            'city_name' => $postal->city_name,
            'organization_site_id' => $organizationSite?->id,
            'organization_site_name' => $organizationSite?->name,
        ];
    }

    public function makePricingSnapshot(
        ServiceCatalog $catalog,
        ServiceZone $zone,
        ZoneServiceRule $rule,
        ZoneCoverageResult $resolution,
        array $data,
    ): array {
        $manualValidationRequired = $resolution->requiresManualValidation();
        $estimatedPrice = Arr::get($data, 'devis_estime');
        $estimatedDuration = Arr::get($data, 'duree_estimee');
        $corporateContext = Arr::get($data, 'corporate_context', []);
        $serviceIdentifier = (string) (
            Arr::get($data, 'service_identifier')
            ?: $catalog->code
            ?: $catalog->slug
        );

        if ($serviceIdentifier === '') {
            throw new \LogicException('Impossible de générer un pricing snapshot sans service_identifier.');
        }
        return [
            'service' => [
                'id' => $catalog->id,
                'code' => $catalog->code,
                'name' => $catalog->name,
                'slug' => $catalog->slug,
                'service_identifier' => $serviceIdentifier,
                'requires_quote' => (bool) $catalog->requires_quote,
                'requires_manual_validation' => (bool) $catalog->requires_manual_validation,
                'is_enterprise' => (bool) $catalog->is_enterprise,
                'default_duration_minutes' => (int) ($catalog->default_duration_minutes ?? 0),
                'base_price' => (float) ($catalog->base_price ?? 0),
            ],
            'rule' => [
                'id' => $rule->id,
                'is_enabled' => (bool) $rule->is_enabled,
                'requires_manual_validation' => (bool) $rule->requires_manual_validation,
                'base_price_override' => $rule->base_price_override !== null ? (float) $rule->base_price_override : null,
                'price_multiplier' => $rule->price_multiplier !== null ? (float) $rule->price_multiplier : null,
                'minimum_notice_hours' => $rule->minimum_notice_hours !== null ? (int) $rule->minimum_notice_hours : null,
                'maximum_daily_capacity' => $rule->maximum_daily_capacity !== null ? (int) $rule->maximum_daily_capacity : null,
                'settings' => $rule->settings,
            ],
            'pricing' => [
                'estimated_price' => $estimatedPrice !== null ? (float) $estimatedPrice : null,
                'estimated_duration_minutes' => $estimatedDuration !== null ? (int) $estimatedDuration : null,
                'travel_surcharge' => (float) ($zone->travel_surcharge ?? 0),
                'applied_base_price' => $rule->base_price_override !== null
                    ? (float) $rule->base_price_override
                    : (float) ($catalog->base_price ?? 0),
                'applied_multiplier' => $rule->price_multiplier !== null ? (float) $rule->price_multiplier : 1.0,
            ],
            'resolution' => [
                'status' => $resolution->status,
                'message' => $resolution->message,
                'source' => $resolution->resolutionSource,
            ],
            'requires_manual_validation' => $manualValidationRequired,
            'corporate_context' => $corporateContext,

            // legacy compatibility
            'service_catalog_id' => $catalog->id,
            'service_name' => $catalog->name,
            'service_identifier' => $serviceIdentifier,
            'base_price' => (float) ($catalog->base_price ?? 0),
            'base_price_override' => $rule->base_price_override !== null ? (float) $rule->base_price_override : null,
            'price_multiplier' => $rule->price_multiplier !== null ? (float) $rule->price_multiplier : null,
            'travel_surcharge' => (float) ($zone->travel_surcharge ?? 0),
            'devis_estime' => $estimatedPrice !== null ? (float) $estimatedPrice : null,
            'duree_estimee' => $estimatedDuration !== null ? (int) $estimatedDuration : null,
        ];
    }
}

<?php

namespace Tests\Support;

use App\Models\Commune;
use App\Models\Country;
use App\Models\Disponibilite;
use App\Models\EmployeeZoneAssignment;
use App\Models\PostalCode;
use App\Models\Province;
use App\Models\Region;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\User;
use App\Models\ZoneServiceRule;

trait CreatesZoneAwareFixtures
{
    protected function createCoverageContext(array $overrides = []): array
    {
        $isoCode = $overrides['country_iso'] ?? 'BE';
        $iso3Code = $overrides['country_iso3'] ?? 'BEL';

        $country = Country::query()
            ->where('iso_code', $isoCode)
            ->orWhere('iso3_code', $iso3Code)
            ->first();

        if (! $country) {
            $country = Country::create([
                'iso_code' => $isoCode,
                'iso3_code' => $iso3Code,
                'name' => $overrides['country_name'] ?? 'Belgique',
                'official_name' => $overrides['country_name'] ?? 'Belgique',
                'default_locale' => 'fr_BE',
                'currency_code' => 'EUR',
                'phone_code' => '+32',
                'timezone' => 'Europe/Brussels',
                'is_active' => true,
            ]);
        }

        $region = Region::firstOrCreate(
            [
                'country_id' => $country->id,
                'code' => $overrides['region_code'] ?? 'BRU',
            ],
            [
                'name' => $overrides['region_name'] ?? 'Bruxelles-Capitale',
                'slug' => $overrides['region_slug'] ?? 'bruxelles-capitale',
                'type' => 'region',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $province = Province::firstOrCreate(
            [
                'country_id' => $country->id,
                'region_id' => $region->id,
                'code' => $overrides['province_code'] ?? 'BRU',
            ],
            [
                'name' => $overrides['province_name'] ?? 'Bruxelles',
                'slug' => $overrides['province_slug'] ?? 'bruxelles',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $commune = Commune::firstOrCreate(
            [
                'country_id' => $country->id,
                'region_id' => $region->id,
                'province_id' => $province->id,
                'name' => $overrides['commune_name'] ?? 'Bruxelles',
            ],
            [
                'nis_code' => $overrides['nis_code'] ?? '21004',
                'slug' => $overrides['commune_slug'] ?? 'bruxelles',
                'is_active' => true,
            ]
        );

        $postalCode = PostalCode::firstOrCreate(
            [
                'country_id' => $country->id,
                'code' => $overrides['postal_code'] ?? '1000',
                'city_name' => $overrides['city_name'] ?? 'Bruxelles',
            ],
            [
                'region_id' => $region->id,
                'province_id' => $province->id,
                'commune_id' => $commune->id,
                'latitude' => 50.8503,
                'longitude' => 4.3517,
                'is_active' => true,
            ]
        );

        $zoneData = array_merge([
            'country_id' => $country->id,
            'region_id' => $region->id,
            'province_id' => $province->id,
            'commune_id' => $commune->id,
            'coverage_type' => 'postal_code',
            'status' => 'active',
            'is_bookable' => true,
            'is_visible' => true,
            'minimum_notice_hours' => 24,
            'maximum_daily_jobs' => 10,
            'time_buffer_minutes' => 15,
            'travel_surcharge' => 5,
        ], $overrides['zone'] ?? []);

        $zone = ServiceZone::factory()->create($zoneData);
        $zone->postalCodes()->syncWithoutDetaching([$postalCode->id => ['is_primary' => true]]);

        $service = ServiceCatalog::factory()->create(array_merge([
            'service_type' => 'nettoyage_standard',
            'is_active' => true,
            'requires_manual_validation' => false,
            'is_entreprise' => false,
            'default_duration_minutes' => 90,
            'base_price' => 79,
        ], $overrides['service'] ?? []));

        $rule = ZoneServiceRule::create(array_merge([
            'service_zone_id' => $zone->id,
            'service_catalog_id' => $service->id,
            'is_enabled' => true,
            'requires_manual_validation' => false,
            'base_price_override' => null,
            'price_multiplier' => 1,
            'minimum_notice_hours' => 24,
            'maximum_daily_capacity' => 5,
            'settings' => null,
        ], $overrides['rule'] ?? []));

        return compact('country', 'region', 'province', 'commune', 'postalCode', 'zone', 'service', 'rule');
    }

    protected function assignEmployeeToZone(User $employee, ServiceZone $zone, array $assignmentOverrides = [], array $availabilityOverrides = []): array
    {
        $defaults = [
            'coverage_priority' => 1,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'notes' => null,
        ];

        $assignmentType = $assignmentOverrides['assignment_type'] ?? 'primary';
        unset($assignmentOverrides['assignment_type']);

        $assignment = EmployeeZoneAssignment::updateOrCreate(
            [
                'user_id' => $employee->id,
                'service_zone_id' => $zone->id,
                'assignment_type' => $assignmentType,
            ],
            array_merge($defaults, $assignmentOverrides)
        );

        $date = $availabilityOverrides['date'] ?? now()->addDays(2)->toDateString();
        $start = $availabilityOverrides['heure_debut'] ?? '09:00:00';
        $end = $availabilityOverrides['heure_fin'] ?? '18:00:00';

        $availability = Disponibilite::create([
            'user_id' => $employee->id,
            'date' => $date,
            'heure_debut' => $start,
            'heure_fin' => $end,
        ]);

        return compact('assignment', 'availability');
    }
}

<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\PostalCode;
use App\Models\Province;
use App\Models\Region;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\ZoneServiceRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ZoneManagementSeeder extends Seeder
{
    public function run(): void
    {
        $country = Country::where('iso_code', 'BE')->firstOrFail();
        $regions = Region::where('country_id', $country->id)->get()->keyBy('code');
        $provinces = Province::where('country_id', $country->id)->get()->keyBy('code');
        $services = ServiceCatalog::all();

        $nationalZone = ServiceZone::updateOrCreate(
            ['code' => 'BE-NATIONAL'],
            [
                'country_id' => $country->id,
                'name' => 'Belgique - Couverture nationale',
                'slug' => 'belgique-couverture-nationale',
                'coverage_type' => 'national',
                'status' => 'active',
                'is_bookable' => true,
                'is_visible' => true,
                'priority' => 10,
                'minimum_notice_hours' => 24,
                'maximum_daily_jobs' => 200,
                'travel_surcharge' => 0,
                'time_buffer_minutes' => 15,
                'activated_at' => now(),
                'metadata' => ['country_scope' => 'BE'],
            ]
        );

        $this->syncRulesForZone($nationalZone, $services, [
            'price_multiplier' => 1.00,
            'maximum_daily_capacity' => 200,
        ]);

        foreach ($regions as $region) {
            $regionZone = ServiceZone::updateOrCreate(
                ['code' => 'ZONE-' . strtoupper(Str::slug($region->code, '-'))],
                [
                    'country_id' => $country->id,
                    'region_id' => $region->id,
                    'parent_zone_id' => $nationalZone->id,
                    'name' => 'Zone ' . $region->name,
                    'slug' => 'zone-region-' . Str::slug($region->name) . '-' . strtolower(str_replace('BE-', '', $region->code)),
                    'coverage_type' => 'region',
                    'status' => 'active',
                    'is_bookable' => true,
                    'is_visible' => true,
                    'priority' => 20,
                    'minimum_notice_hours' => $region->code === 'BE-BRU' ? 12 : 24,
                    'maximum_daily_jobs' => $region->code === 'BE-BRU' ? 60 : 120,
                    'time_buffer_minutes' => $region->code === 'BE-BRU' ? 20 : 30,
                    'activated_at' => now(),
                ]
            );

            $this->syncRulesForZone($regionZone, $services, [
                'price_multiplier' => $region->code === 'BE-BRU' ? 1.10 : 1.00,
                'minimum_notice_hours' => $region->code === 'BE-BRU' ? 12 : null,
                'maximum_daily_capacity' => $region->code === 'BE-BRU' ? 60 : 120,
            ]);
        }

        foreach ($provinces as $province) {
            $zone = ServiceZone::updateOrCreate(
                ['code' => 'PROV-' . $province->code],
                [
                    'country_id' => $country->id,
                    'region_id' => $province->region_id,
                    'province_id' => $province->id,
                    'parent_zone_id' => $nationalZone->id,
                    'name' => 'Zone ' . $province->name,
                    'slug' => 'zone-province-' . Str::slug($province->name) . '-' . strtolower($province->code),
                    'coverage_type' => 'province',
                    'status' => 'active',
                    'is_bookable' => true,
                    'is_visible' => true,
                    'priority' => 30,
                    'minimum_notice_hours' => $province->code === 'BRU' ? 12 : 24,
                    'maximum_daily_jobs' => $province->code === 'BRU' ? 40 : 25,
                    'travel_surcharge' => in_array($province->code, ['LUX', 'NAM'], true) ? 12.50 : 0,
                    'time_buffer_minutes' => in_array($province->code, ['BRU', 'ANT'], true) ? 20 : 30,
                    'activated_at' => now(),
                ]
            );

            $postalCodes = PostalCode::where('province_id', $province->id)->get();
            if ($postalCodes->isNotEmpty()) {
                $syncPayload = [];
                foreach ($postalCodes as $index => $postalCode) {
                    $syncPayload[$postalCode->id] = ['is_primary' => $index === 0];
                }
                $zone->postalCodes()->syncWithoutDetaching($syncPayload);
            }

            $this->syncRulesForZone($zone, $services, [
                'price_multiplier' => $province->code === 'BRU' ? 1.10 : ($province->code === 'LUX' ? 1.08 : 1.00),
                'minimum_notice_hours' => $province->code === 'BRU' ? 12 : null,
                'maximum_daily_capacity' => null,
            ], function ($service) {
                return $service->is_entreprise ? 12 : 20;
            });
        }

        $this->command?->info('✅ Zones de service Belgique initialisées.');
    }

    protected function syncRulesForZone(ServiceZone $zone, Collection $services, array $overrides = [], ?callable $capacityResolver = null): void
    {
        foreach ($services as $service) {
            ZoneServiceRule::updateOrCreate(
                [
                    'service_zone_id' => $zone->id,
                    'service_catalog_id' => $service->id,
                ],
                [
                    'is_enabled' => true,
                    'requires_manual_validation' => $service->requires_manual_validation,
                    'base_price_override' => null,
                    'price_multiplier' => $overrides['price_multiplier'] ?? 1.00,
                    'minimum_notice_hours' => $overrides['minimum_notice_hours'] ?? null,
                    'maximum_daily_capacity' => $capacityResolver ? $capacityResolver($service) : ($overrides['maximum_daily_capacity'] ?? 20),
                    'settings' => ['seeded' => true],
                ]
            );
        }
    }
}

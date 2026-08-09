<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsOnlyExistingColumns;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ZoneManagementSeeder extends Seeder
{
    use SeedsOnlyExistingColumns;

    public function run(): void
    {
        if (! $this->hasTable('service_zones')) {
            $this->command?->warn('⚠️ Table service_zones absente, ZoneManagementSeeder ignoré.');

            return;
        }

        $country = DB::table('countries')->where('iso_code', 'BE')->first();

        if (! $country) {
            $this->command?->warn('⚠️ Pays BE introuvable, lance BelgiumGeographySeeder avant ZoneManagementSeeder.');

            return;
        }

        $postalCodes = $this->hasTable('postal_codes')
            ? DB::table('postal_codes')->where('country_id', $country->id)->get()
            : collect();

        $services = $this->hasTable('service_catalogs')
            ? DB::table('service_catalogs')->get()
            : collect();

        $nationalZone = $this->updateOrInsertTable(
            'service_zones',
            ['slug' => 'belgique-couverture-nationale'],
            [
                'code' => 'BE-NATIONAL',
                'country_id' => $country->id,
                'name' => 'Belgique - Couverture nationale',
                'coverage_type' => 'national',
                'status' => 'active',
                'is_bookable' => true,
                'is_visible' => true,
                'priority' => 10,
                'minimum_notice_hours' => 24,
                'maximum_daily_jobs' => 200,
                'max_bookings_per_day' => 200,
                'travel_surcharge' => 0,
                'time_buffer_minutes' => 15,
                'activated_at' => now(),
                'coverage_postal_codes' => $postalCodes->pluck('code')->unique()->values()->all(),
                'metadata' => ['code' => 'BE-NATIONAL', 'country_scope' => 'BE', 'seeded' => true],
            ]
        );

        if ($nationalZone) {
            // La couverture nationale reçoit TOUS les codes postaux : c'est le dernier recours de
            // `ZoneCoverageService`, et un recours sans code postal ne rattrape rien.
            $this->attachPostalCodes((int) $nationalZone->id, $postalCodes, $postalCodes->pluck('code')->all());

            $this->syncRulesForZone((int) $nationalZone->id, $services, [
                'price_multiplier' => 1.00,
                'minimum_notice_hours' => 24,
                'max_bookings_per_day' => 200,
                'maximum_daily_capacity' => 200,
            ]);
        }

        $cityZones = [
            'BRU' => ['name' => 'Zone Bruxelles', 'slug' => 'zone-bruxelles', 'postal_prefixes' => ['10'], 'multiplier' => 1.10, 'capacity' => 60, 'notice' => 12],
            'ANT' => ['name' => 'Zone Anvers', 'slug' => 'zone-anvers', 'postal_prefixes' => ['20'], 'multiplier' => 1.05, 'capacity' => 40, 'notice' => 24],
            'GNT' => ['name' => 'Zone Gand', 'slug' => 'zone-gand', 'postal_prefixes' => ['90'], 'multiplier' => 1.00, 'capacity' => 35, 'notice' => 24],
            'LIE' => ['name' => 'Zone Liège', 'slug' => 'zone-liege', 'postal_prefixes' => ['40'], 'multiplier' => 1.00, 'capacity' => 30, 'notice' => 24],
            'NAM' => ['name' => 'Zone Namur', 'slug' => 'zone-namur', 'postal_prefixes' => ['50'], 'multiplier' => 1.04, 'capacity' => 25, 'notice' => 24],
        ];

        foreach ($cityZones as $code => $zoneData) {
            $zonePostalCodes = $postalCodes
                ->filter(fn ($postalCode) => collect($zoneData['postal_prefixes'])->contains(fn ($prefix) => str_starts_with((string) $postalCode->code, $prefix)))
                ->pluck('code')
                ->unique()
                ->values();

            $zone = $this->updateOrInsertTable(
                'service_zones',
                ['slug' => $zoneData['slug']],
                [
                    'code' => 'PROV-'.$code,
                    'country_id' => $country->id,
                    'parent_zone_id' => $nationalZone?->id,
                    'name' => $zoneData['name'],
                    'coverage_type' => 'city_cluster',
                    'status' => 'active',
                    'is_bookable' => true,
                    'is_visible' => true,
                    'priority' => 20,
                    'minimum_notice_hours' => $zoneData['notice'],
                    'maximum_daily_jobs' => $zoneData['capacity'],
                    'max_bookings_per_day' => $zoneData['capacity'],
                    'time_buffer_minutes' => 20,
                    'activated_at' => now(),
                    'coverage_postal_codes' => $zonePostalCodes->all(),
                    'metadata' => ['code' => 'PROV-'.$code, 'seeded' => true],
                ]
            );

            if (! $zone) {
                continue;
            }

            if ($this->hasColumn('postal_codes', 'service_zone_id')) {
                DB::table('postal_codes')->whereIn('code', $zonePostalCodes->all())->update(['service_zone_id' => $zone->id]);
            }

            $this->attachPostalCodes((int) $zone->id, $postalCodes, $zonePostalCodes->all());

            $this->syncRulesForZone((int) $zone->id, $services, [
                'price_multiplier' => $zoneData['multiplier'],
                'minimum_notice_hours' => $zoneData['notice'],
                'max_bookings_per_day' => $zoneData['capacity'],
                'maximum_daily_capacity' => $zoneData['capacity'],
            ]);
        }

        $this->command?->info('✅ Zones de service initialisées selon les migrations disponibles.');
    }

    /**
     * LE PIVOT QUI FAISAIT DÉFAUT — `service_zone_postal_code`, et ses zéro lignes.
     *
     * `ZoneCoverageService::resolveServiceZoneWithSource()` cherche la zone par ce pivot AVANT tout
     * repli. La table existait, personne ne la remplissait : la résolution tombait donc
     * systématiquement sur le repli provincial puis national, et deux adresses de villes
     * différentes obtenaient la même zone — donc le même prix, et le même bassin de prestataires.
     * C'est le maillon coupé de la chaîne géographique.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $allPostalCodes
     * @param  list<string>  $codes
     */
    protected function attachPostalCodes(int $zoneId, $allPostalCodes, array $codes): void
    {
        if (! $this->hasTable('service_zone_postal_code') || $codes === []) {
            return;
        }

        $ids = $allPostalCodes
            ->filter(fn ($postalCode) => in_array($postalCode->code, $codes, true))
            ->pluck('id');

        $aPrimaire = $this->hasColumn('service_zone_postal_code', 'is_primary');

        foreach ($ids as $postalCodeId) {
            $existe = DB::table('service_zone_postal_code')
                ->where('service_zone_id', $zoneId)
                ->where('postal_code_id', $postalCodeId)
                ->exists();

            if ($existe) {
                continue;
            }

            $ligne = [
                'service_zone_id' => $zoneId,
                'postal_code_id' => $postalCodeId,
            ];

            if ($aPrimaire) {
                $ligne['is_primary'] = true;
            }

            if ($this->hasColumn('service_zone_postal_code', 'created_at')) {
                $ligne['created_at'] = now();
                $ligne['updated_at'] = now();
            }

            DB::table('service_zone_postal_code')->insert($ligne);
        }
    }

    protected function syncRulesForZone(int $zoneId, $services, array $overrides = []): void
    {
        if (! $this->hasTable('zone_service_rules')) {
            return;
        }

        foreach ($services as $service) {
            $this->updateOrInsertTable(
                'zone_service_rules',
                [
                    'service_zone_id' => $zoneId,
                    'service_catalog_id' => $service->id,
                ],
                [
                    'is_enabled' => true,
                    'requires_manual_validation' => (bool) ($service->requires_manual_validation ?? false),
                    'base_price_override' => null,
                    'price_multiplier' => $overrides['price_multiplier'] ?? 1.00,
                    'minimum_notice_hours' => $overrides['minimum_notice_hours'] ?? 24,
                    'maximum_daily_capacity' => $overrides['maximum_daily_capacity'] ?? 20,
                    'max_bookings_per_day' => $overrides['max_bookings_per_day'] ?? 20,
                    'settings' => ['seeded' => true],
                ]
            );
        }
    }
}

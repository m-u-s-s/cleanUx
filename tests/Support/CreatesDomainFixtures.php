<?php

namespace Tests\Support;

use App\Models\Commune;
use App\Models\Country;
use App\Models\EmployeeZoneAssignment;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\PostalCode;
use App\Models\Province;
use App\Models\Region;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\User;
use App\Models\ZoneServiceRule;

trait CreatesDomainFixtures
{
    protected static int $fixtureSequence = 1;

    protected function nextFixtureId(): int
    {
        return self::$fixtureSequence++;
    }

    protected function createGeoContext(): array
    {
        $i = $this->nextFixtureId();

        $country = Country::create([
            'iso_code' => 'X'.($i % 10),
            'iso3_code' => 'X'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'name' => 'Country '.$i,
            'official_name' => 'Official Country '.$i,
            'default_locale' => 'fr_BE',
            'currency_code' => 'EUR',
            'phone_code' => '+32',
            'timezone' => 'Europe/Brussels',
            'is_active' => true,
        ]);

        $region = Region::create([
            'country_id' => $country->id,
            'code' => 'REG'.$i,
            'name' => 'Region '.$i,
            'slug' => 'region-'.$i,
            'type' => 'region',
            'sort_order' => $i,
            'is_active' => true,
        ]);

        $province = Province::create([
            'country_id' => $country->id,
            'region_id' => $region->id,
            'code' => 'PROV'.$i,
            'name' => 'Province '.$i,
            'slug' => 'province-'.$i,
            'sort_order' => $i,
            'is_active' => true,
        ]);

        $commune = Commune::create([
            'country_id' => $country->id,
            'region_id' => $region->id,
            'province_id' => $province->id,
            'nis_code' => 'NIS'.$i,
            'name' => 'Commune '.$i,
            'slug' => 'commune-'.$i,
            'is_active' => true,
        ]);

        $postalCode = PostalCode::create([
            'country_id' => $country->id,
            'region_id' => $region->id,
            'province_id' => $province->id,
            'commune_id' => $commune->id,
            'code' => '10'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'city_name' => 'Ville '.$i,
            'latitude' => 50.85,
            'longitude' => 4.35,
            'is_active' => true,
        ]);

        return compact('country', 'region', 'province', 'commune', 'postalCode');
    }

    protected function createZoneContext(): array
    {
        $geo = $this->createGeoContext();
        $i = $this->nextFixtureId();

        $zone = ServiceZone::create([
            'country_id' => $geo['country']->id,
            'region_id' => $geo['region']->id,
            'province_id' => $geo['province']->id,
            'commune_id' => $geo['commune']->id,
            'parent_zone_id' => null,
            'code' => 'ZONE'.$i,
            'name' => 'Zone '.$i,
            'slug' => 'zone-'.$i,
            'coverage_type' => 'postal_code',
            'status' => 'active',
            'is_bookable' => true,
            'is_visible' => true,
            'priority' => 10,
            'minimum_notice_hours' => 24,
            'maximum_daily_jobs' => 8,
            'travel_surcharge' => 5.50,
            'time_buffer_minutes' => 30,
            'metadata' => ['source' => 'tests'],
            'notes' => 'Zone de test',
            'activated_at' => now(),
            'deactivated_at' => null,
        ]);

        $zone->postalCodes()->attach($geo['postalCode']->id, ['is_primary' => true]);

        $service = ServiceCatalog::create([
            'code' => 'SERVICE'.$i,
            'name' => 'Service '.$i,
            'slug' => 'service-'.$i,
            'description' => 'Service de test',
            'service_type' => 'nettoyage_standard',
            'is_active' => true,
            'requires_quote' => false,
            'requires_manual_validation' => false,
            'is_entreprise' => false,
            'default_duration_minutes' => 120,
            'base_price' => 89.90,
            'sort_order' => 1,
            'settings' => ['tests' => true],
        ]);

        $rule = ZoneServiceRule::create([
            'service_zone_id' => $zone->id,
            'service_catalog_id' => $service->id,
            'is_enabled' => true,
            'requires_manual_validation' => false,
            'base_price_override' => 99.90,
            'price_multiplier' => 1.10,
            'minimum_notice_hours' => 24,
            'maximum_daily_capacity' => 4,
            'settings' => ['tests' => true],
        ]);

        return $geo + compact('zone', 'service', 'rule');
    }

    protected function createEntrepriseContext(?User $clientUser = null): array
    {
        $context = $this->createZoneContext();
        $i = $this->nextFixtureId();

        $clientUser ??= User::factory()->client()->create();

        $account = OrganizationAccount::create([
            'country_id' => $context['country']->id,
            'region_id' => $context['region']->id,
            'province_id' => $context['province']->id,
            'commune_id' => $context['commune']->id,
            'postal_code_id' => $context['postalCode']->id,
            'name' => 'Organisation '.$i,
            'legal_name' => 'Organisation Legale '.$i,
            'slug' => 'organisation-'.$i,
            'type' => 'entreprise',
            'tva_number' => 'BE0'.str_pad((string) $i, 9, '0', STR_PAD_LEFT),
            'email' => 'org'.$i.'@example.test',
            'phone' => '0102030405',
            'billing_email' => 'billing'.$i.'@example.test',
            'status' => 'active',
            'address_line_1' => 'Rue Entreprise '.$i,
            'address_line_2' => null,
            'city' => $context['postalCode']->city_name,
            'postal_code' => $context['postalCode']->code,
            'is_multisite' => true,
            'is_key_account' => false,
            'metadata' => ['tests' => true],
            'notes' => 'Compte entreprise de test',
        ]);

        $site = OrganizationSite::create([
            'organization_account_id' => $account->id,
            'client_user_id' => $clientUser->id,
            'service_zone_id' => $context['zone']->id,
            'postal_code_id' => $context['postalCode']->id,
            'name' => 'Site '.$i,
            'site_code' => 'SITE'.$i,
            'contact_name' => 'Contact '.$i,
            'email' => 'site'.$i.'@example.test',
            'phone' => '0102030405',
            'address_line_1' => 'Rue Site '.$i,
            'address_line_2' => null,
            'city' => $context['postalCode']->city_name,
            'postal_code' => $context['postalCode']->code,
            'access_instructions' => 'Accès standard',
            'latitude' => 50.85,
            'longitude' => 4.35,
            'is_primary' => true,
            'is_active' => true,
            'metadata' => ['tests' => true],
        ]);

        return $context + compact('account', 'site', 'clientUser');
    }

    protected function assignEmployeToZone(User $employe, ServiceZone $zone, string $type = 'primary'): EmployeeZoneAssignment
    {
        return EmployeeZoneAssignment::create([
            'user_id' => $employe->id,
            'service_zone_id' => $zone->id,
            'assignment_type' => $type,
            'coverage_priority' => 1,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'notes' => 'Affectation de test',
        ]);
    }
}

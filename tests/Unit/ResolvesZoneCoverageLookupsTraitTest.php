<?php

namespace Tests\Unit;

use App\Models\OrganizationSite;
use App\Models\PostalCode;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\ZoneServiceRule;
use App\Services\Booking\Concerns\ResolvesZoneCoverageLookups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolvesZoneCoverageLookupsTraitHost
{
    use ResolvesZoneCoverageLookups;
}

class ResolvesZoneCoverageLookupsTraitTest extends TestCase
{
    use RefreshDatabase;

    private ResolvesZoneCoverageLookupsTraitHost $host;

    protected function setUp(): void
    {
        parent::setUp();
        $this->host = new ResolvesZoneCoverageLookupsTraitHost;
    }

    public function test_resolve_postal_code_returns_null_for_blank_input(): void
    {
        $this->assertNull($this->host->resolvePostalCode(null));
        $this->assertNull($this->host->resolvePostalCode('   '));
    }

    public function test_resolve_postal_code_matches_by_code_only(): void
    {
        $postal = PostalCode::factory()->create(['code' => '1000', 'city_name' => 'Bruxelles']);

        $resolved = $this->host->resolvePostalCode('1000');

        $this->assertNotNull($resolved);
        $this->assertSame($postal->id, $resolved->id);
    }

    public function test_resolve_postal_code_matches_by_code_and_city(): void
    {
        PostalCode::factory()->create(['code' => '1000', 'city_name' => 'Bruxelles']);
        $other = PostalCode::factory()->create(['code' => '1000', 'city_name' => 'Brussel']);

        $resolved = $this->host->resolvePostalCode('1000', 'Brussel');

        $this->assertNotNull($resolved);
        $this->assertSame($other->id, $resolved->id);
    }

    public function test_resolve_postal_code_falls_back_to_code_when_city_does_not_match(): void
    {
        $postal = PostalCode::factory()->create(['code' => '4000', 'city_name' => 'Liège']);

        $resolved = $this->host->resolvePostalCode('4000', 'Nowhere City');

        $this->assertNotNull($resolved);
        $this->assertSame($postal->id, $resolved->id);
    }

    public function test_resolve_postal_code_ignores_inactive_records(): void
    {
        PostalCode::factory()->create(['code' => '9999', 'city_name' => 'Ghost', 'is_active' => false]);

        $this->assertNull($this->host->resolvePostalCode('9999'));
    }

    public function test_resolve_service_catalog_returns_null_for_blank_identifier(): void
    {
        $this->assertNull($this->host->resolveServiceCatalog('   '));
    }

    public function test_resolve_service_catalog_matches_by_service_type(): void
    {
        $catalog = ServiceCatalog::factory()->create(['service_type' => 'deep-cleaning', 'is_active' => true]);

        $resolved = $this->host->resolveServiceCatalog('deep-cleaning');

        $this->assertNotNull($resolved);
        $this->assertSame($catalog->id, $resolved->id);
    }

    public function test_resolve_service_catalog_matches_case_insensitively_by_code(): void
    {
        $catalog = ServiceCatalog::factory()->create(['code' => 'SVC-ABC', 'is_active' => true]);

        $resolved = $this->host->resolveServiceCatalog('svc-abc');

        $this->assertNotNull($resolved);
        $this->assertSame($catalog->id, $resolved->id);
    }

    public function test_resolve_service_catalog_uses_single_zone_rule_fallback(): void
    {
        $zone = ServiceZone::factory()->create();
        $catalog = ServiceCatalog::factory()->create(['is_active' => true]);
        ZoneServiceRule::factory()->create([
            'service_zone_id' => $zone->id,
            'service_catalog_id' => $catalog->id,
            'is_enabled' => true,
            'price_multiplier' => 1.0,
            'minimum_notice_hours' => 24,
        ]);

        $resolved = $this->host->resolveServiceCatalog('totally-unknown-service', $zone);

        $this->assertNotNull($resolved);
        $this->assertSame($catalog->id, $resolved->id);
    }

    public function test_resolve_service_catalog_returns_null_when_zone_has_multiple_rules(): void
    {
        $zone = ServiceZone::factory()->create();
        ZoneServiceRule::factory()->count(2)->create([
            'service_zone_id' => $zone->id,
            'is_enabled' => true,
            'price_multiplier' => 1.0,
            'minimum_notice_hours' => 24,
        ]);

        $this->assertNull($this->host->resolveServiceCatalog('totally-unknown-service', $zone));
    }

    public function test_resolve_service_catalog_returns_null_without_match_or_zone(): void
    {
        ServiceCatalog::factory()->create(['service_type' => 'standard', 'is_active' => true]);

        $this->assertNull($this->host->resolveServiceCatalog('does-not-exist'));
    }

    public function test_resolve_service_zone_with_source_returns_null_when_nothing_provided(): void
    {
        $result = $this->host->resolveServiceZoneWithSource(null, null);

        $this->assertNull($result['zone']);
        $this->assertNull($result['source']);
    }

    public function test_resolve_service_zone_prefers_organization_site(): void
    {
        $zone = ServiceZone::factory()->create(['status' => 'active', 'is_bookable' => true]);
        $site = OrganizationSite::factory()->create(['service_zone_id' => $zone->id]);

        $result = $this->host->resolveServiceZoneWithSource(null, $site);

        $this->assertNotNull($result['zone']);
        $this->assertSame($zone->id, $result['zone']->id);
        $this->assertSame('organization_site', $result['source']);
    }

    public function test_resolve_service_zone_matches_attached_postal_code(): void
    {
        $postal = PostalCode::factory()->create();
        $zone = ServiceZone::factory()->create(['status' => 'active', 'is_bookable' => true]);
        $zone->postalCodes()->attach($postal->id, ['is_primary' => true]);

        $result = $this->host->resolveServiceZoneWithSource($postal);

        $this->assertNotNull($result['zone']);
        $this->assertSame($zone->id, $result['zone']->id);
        $this->assertSame('postal_code', $result['source']);
    }

    public function test_resolve_service_zone_falls_back_to_province(): void
    {
        $postal = PostalCode::factory()->create();
        $zone = ServiceZone::factory()->create([
            'province_id' => $postal->province_id,
            'status' => 'active',
            'is_bookable' => true,
        ]);

        $result = $this->host->resolveServiceZoneWithSource($postal);

        $this->assertSame($zone->id, $result['zone']->id);
        $this->assertSame('province_fallback', $result['source']);
    }

    public function test_resolve_service_zone_falls_back_to_region(): void
    {
        $postal = PostalCode::factory()->create();
        $zone = ServiceZone::factory()->create([
            'province_id' => null,
            'region_id' => $postal->region_id,
            'status' => 'active',
            'is_bookable' => true,
        ]);

        $result = $this->host->resolveServiceZoneWithSource($postal);

        $this->assertSame($zone->id, $result['zone']->id);
        $this->assertSame('region_fallback', $result['source']);
    }

    public function test_resolve_service_zone_falls_back_to_national(): void
    {
        $postal = PostalCode::factory()->create();
        $zone = ServiceZone::factory()->create([
            'province_id' => null,
            'region_id' => null,
            'coverage_type' => 'national',
            'status' => 'active',
            'is_bookable' => true,
        ]);

        $result = $this->host->resolveServiceZoneWithSource($postal);

        $this->assertSame($zone->id, $result['zone']->id);
        $this->assertSame('national_fallback', $result['source']);
    }

    public function test_resolve_service_zone_bookable_only_excludes_non_bookable(): void
    {
        $postal = PostalCode::factory()->create();
        $zone = ServiceZone::factory()->create(['status' => 'active', 'is_bookable' => false]);
        $zone->postalCodes()->attach($postal->id, ['is_primary' => true]);

        $lenient = $this->host->resolveServiceZone($postal, null, false);
        $this->assertNotNull($lenient);
        $this->assertSame($zone->id, $lenient->id);

        $strict = $this->host->resolveServiceZone($postal, null, true);
        $this->assertNull($strict);
    }

    public function test_resolve_zone_service_rule_returns_null_without_zone_or_catalog(): void
    {
        $zone = ServiceZone::factory()->create();
        $catalog = ServiceCatalog::factory()->create();

        $this->assertNull($this->host->resolveZoneServiceRule(null, $catalog));
        $this->assertNull($this->host->resolveZoneServiceRule($zone, null));
    }

    public function test_resolve_zone_service_rule_returns_enabled_rule(): void
    {
        $zone = ServiceZone::factory()->create();
        $catalog = ServiceCatalog::factory()->create();
        $rule = ZoneServiceRule::factory()->create([
            'service_zone_id' => $zone->id,
            'service_catalog_id' => $catalog->id,
            'is_enabled' => true,
            'price_multiplier' => 1.0,
            'minimum_notice_hours' => 24,
        ]);

        $resolved = $this->host->resolveZoneServiceRule($zone, $catalog);

        $this->assertNotNull($resolved);
        $this->assertSame($rule->id, $resolved->id);
    }
}

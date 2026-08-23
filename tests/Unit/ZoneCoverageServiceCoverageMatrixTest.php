<?php

namespace Tests\Unit;

use App\Models\OrganizationSite;
use App\Models\PostalCode;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\ZoneServiceRule;
use App\Services\Booking\ZoneCoverageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZoneCoverageServiceCoverageMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ZoneCoverageService
    {
        return app(ZoneCoverageService::class);
    }

    private function attach(ServiceZone $zone, PostalCode $postalCode, bool $primary = true): void
    {
        $zone->postalCodes()->attach($postalCode->id, ['is_primary' => $primary]);
    }

    /**
     * The ZoneServiceRule factory leaves several NOT NULL columns optional; supply safe non-null defaults so inserts never violate the schema.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function makeRule(array $attributes): ZoneServiceRule
    {
        return ZoneServiceRule::factory()->create(array_merge([
            'base_price_override' => 100.00,
            'price_multiplier' => 1.0,
            'minimum_notice_hours' => 24,
            'maximum_daily_capacity' => 5,
        ], $attributes));
    }

    // ---------------------------------------------------------------------
    // resolveServiceCatalog
    // ---------------------------------------------------------------------

    public function test_resolve_service_catalog_returns_null_for_blank_identifier(): void
    {
        $this->assertNull($this->service()->resolveServiceCatalog(null));
        $this->assertNull($this->service()->resolveServiceCatalog('   '));
    }

    public function test_resolve_service_catalog_matches_by_code_and_is_case_insensitive(): void
    {
        $catalog = ServiceCatalog::factory()->create([
            'code' => 'SVC-MATRIX',
            'slug' => 'matrix-cleaning',
            'service_type' => 'deep-cleaning',
            'is_active' => true,
        ]);

        $byCode = $this->service()->resolveServiceCatalog('SVC-MATRIX');
        $bySlug = $this->service()->resolveServiceCatalog('matrix-cleaning');
        $byTypeLower = $this->service()->resolveServiceCatalog('DEEP-CLEANING');

        $this->assertSame($catalog->id, $byCode?->id);
        $this->assertSame($catalog->id, $bySlug?->id);
        $this->assertSame($catalog->id, $byTypeLower?->id);
    }

    public function test_resolve_service_catalog_ignores_inactive_catalog(): void
    {
        ServiceCatalog::factory()->create([
            'code' => 'SVC-OFF',
            'is_active' => false,
        ]);

        $this->assertNull($this->service()->resolveServiceCatalog('SVC-OFF'));
    }

    public function test_resolve_service_catalog_falls_back_to_single_zone_rule(): void
    {
        $zone = ServiceZone::factory()->create();
        $catalog = ServiceCatalog::factory()->create([
            'code' => 'SVC-ONLY',
            'is_active' => true,
        ]);
        $this->makeRule([
            'service_zone_id' => $zone->id,
            'service_catalog_id' => $catalog->id,
            'is_enabled' => true,
        ]);

        // "garbage" matches no catalog directly, but the zone has exactly one enabled rule.
        $resolved = $this->service()->resolveServiceCatalog('does-not-match-anything', $zone);

        $this->assertSame($catalog->id, $resolved?->id);
    }

    public function test_resolve_service_catalog_no_fallback_when_zone_has_multiple_rules(): void
    {
        $zone = ServiceZone::factory()->create();
        $this->makeRule(['service_zone_id' => $zone->id, 'is_enabled' => true]);
        $this->makeRule(['service_zone_id' => $zone->id, 'is_enabled' => true]);

        $this->assertNull($this->service()->resolveServiceCatalog('nothing-here', $zone));
    }

    // ---------------------------------------------------------------------
    // resolveServiceZoneWithSource
    // ---------------------------------------------------------------------

    public function test_resolve_zone_returns_null_when_no_postal_and_no_site_zone(): void
    {
        $result = $this->service()->resolveServiceZoneWithSource(null, null);

        $this->assertNull($result['zone']);
        $this->assertNull($result['source']);
    }

    public function test_resolve_zone_prefers_organization_site_zone(): void
    {
        $zone = ServiceZone::factory()->create(['status' => 'active']);
        $site = OrganizationSite::factory()->create(['service_zone_id' => $zone->id]);

        $result = $this->service()->resolveServiceZoneWithSource(null, $site);

        $this->assertSame($zone->id, $result['zone']?->id);
        $this->assertSame('organization_site', $result['source']);
    }

    public function test_resolve_zone_matches_via_postal_code_pivot(): void
    {
        $postalCode = PostalCode::factory()->create();
        $zone = ServiceZone::factory()->create(['status' => 'active']);
        $this->attach($zone, $postalCode);

        $result = $this->service()->resolveServiceZoneWithSource($postalCode);

        $this->assertSame($zone->id, $result['zone']?->id);
        $this->assertSame('postal_code', $result['source']);
    }

    public function test_resolve_zone_province_fallback(): void
    {
        $postalCode = PostalCode::factory()->create();
        $zone = ServiceZone::factory()->create([
            'status' => 'active',
            'province_id' => $postalCode->province_id,
        ]);

        $result = $this->service()->resolveServiceZoneWithSource($postalCode);

        $this->assertSame($zone->id, $result['zone']?->id);
        $this->assertSame('province_fallback', $result['source']);
    }

    public function test_resolve_zone_region_fallback(): void
    {
        $postalCode = PostalCode::factory()->create(['province_id' => null]);
        $zone = ServiceZone::factory()->create([
            'status' => 'active',
            'region_id' => $postalCode->region_id,
        ]);

        $result = $this->service()->resolveServiceZoneWithSource($postalCode);

        $this->assertSame($zone->id, $result['zone']?->id);
        $this->assertSame('region_fallback', $result['source']);
    }

    public function test_resolve_zone_national_fallback(): void
    {
        $postalCode = PostalCode::factory()->create(['province_id' => null, 'region_id' => null]);
        $zone = ServiceZone::factory()->create([
            'status' => 'active',
            'coverage_type' => 'national',
        ]);

        $result = $this->service()->resolveServiceZoneWithSource($postalCode);

        $this->assertSame($zone->id, $result['zone']?->id);
        $this->assertSame('national_fallback', $result['source']);
    }

    public function test_resolve_zone_bookable_only_excludes_paused_zone(): void
    {
        $postalCode = PostalCode::factory()->create();
        $zone = ServiceZone::factory()->create([
            'status' => 'paused',
            'is_bookable' => true,
        ]);
        $this->attach($zone, $postalCode);

        // bookableOnly=false keeps paused zones reachable...
        $loose = $this->service()->resolveServiceZoneWithSource($postalCode, null, false);
        $this->assertSame($zone->id, $loose['zone']?->id);

        // ...but bookableOnly=true requires status=active, so this postal yields no zone.
        $strict = $this->service()->resolveServiceZoneWithSource($postalCode, null, true);
        $this->assertNull($strict['zone']);
    }

    public function test_resolve_service_zone_convenience_returns_zone_only(): void
    {
        $zone = ServiceZone::factory()->create(['status' => 'active']);
        $site = OrganizationSite::factory()->create(['service_zone_id' => $zone->id]);

        $resolved = $this->service()->resolveServiceZone(null, $site);

        $this->assertInstanceOf(ServiceZone::class, $resolved);
        $this->assertSame($zone->id, $resolved->id);
    }

    // ---------------------------------------------------------------------
    // resolveZoneServiceRule
    // ---------------------------------------------------------------------

    public function test_resolve_zone_service_rule_returns_null_without_zone_or_catalog(): void
    {
        $zone = ServiceZone::factory()->create();
        $catalog = ServiceCatalog::factory()->create();

        $this->assertNull($this->service()->resolveZoneServiceRule(null, $catalog));
        $this->assertNull($this->service()->resolveZoneServiceRule($zone, null));
    }

    public function test_resolve_zone_service_rule_finds_enabled_rule(): void
    {
        $zone = ServiceZone::factory()->create();
        $catalog = ServiceCatalog::factory()->create();
        $rule = $this->makeRule([
            'service_zone_id' => $zone->id,
            'service_catalog_id' => $catalog->id,
            'is_enabled' => true,
        ]);

        $resolved = $this->service()->resolveZoneServiceRule($zone, $catalog);

        $this->assertSame($rule->id, $resolved?->id);
    }

    public function test_resolve_zone_service_rule_ignores_disabled_rule(): void
    {
        $zone = ServiceZone::factory()->create();
        $catalog = ServiceCatalog::factory()->create();
        $this->makeRule([
            'service_zone_id' => $zone->id,
            'service_catalog_id' => $catalog->id,
            'is_enabled' => false,
        ]);

        $this->assertNull($this->service()->resolveZoneServiceRule($zone, $catalog));
    }

    // ---------------------------------------------------------------------
    // resolveCoverage (full status matrix)
    // ---------------------------------------------------------------------

    public function test_resolve_coverage_invalid_postal_code(): void
    {
        $result = $this->service()->resolveCoverage('00000', null, null);

        $this->assertSame('invalid_postal_code', $result->status);
        $this->assertNull($result->postalCode);
        $this->assertNull($result->zone);
        $this->assertFalse($result->isBookable());
    }

    public function test_resolve_coverage_unsupported_when_no_zone(): void
    {
        $postalCode = PostalCode::factory()->create([
            'code' => '7777',
            'province_id' => null,
            'region_id' => null,
        ]);

        $result = $this->service()->resolveCoverage('7777', null, null);

        $this->assertSame('unsupported', $result->status);
        $this->assertSame($postalCode->id, $result->postalCode?->id);
        $this->assertNull($result->zone);
    }

    public function test_resolve_coverage_paused_zone(): void
    {
        $postalCode = PostalCode::factory()->create(['code' => '7001']);
        $zone = ServiceZone::factory()->create(['status' => 'paused']);
        $this->attach($zone, $postalCode);

        $result = $this->service()->resolveCoverage('7001', null, null);

        $this->assertSame('paused', $result->status);
        $this->assertSame($zone->id, $result->zone?->id);
    }

    public function test_resolve_coverage_hidden_zone(): void
    {
        $postalCode = PostalCode::factory()->create(['code' => '7002']);
        $zone = ServiceZone::factory()->create([
            'status' => 'active',
            'is_visible' => false,
            'is_bookable' => false,
        ]);
        $this->attach($zone, $postalCode);

        $result = $this->service()->resolveCoverage('7002', null, null);

        $this->assertSame('hidden', $result->status);
    }

    public function test_resolve_coverage_non_bookable_zone(): void
    {
        $postalCode = PostalCode::factory()->create(['code' => '7003']);
        $zone = ServiceZone::factory()->create([
            'status' => 'active',
            'is_visible' => true,
            'is_bookable' => false,
        ]);
        $this->attach($zone, $postalCode);

        $result = $this->service()->resolveCoverage('7003', null, null);

        $this->assertSame('non_bookable', $result->status);
    }

    public function test_resolve_coverage_service_unavailable_when_catalog_missing(): void
    {
        $postalCode = PostalCode::factory()->create(['code' => '7004']);
        $zone = ServiceZone::factory()->create([
            'status' => 'active',
            'is_visible' => true,
            'is_bookable' => true,
        ]);
        $this->attach($zone, $postalCode);

        $result = $this->service()->resolveCoverage('7004', null, 'no-such-service');

        $this->assertSame('service_unavailable', $result->status);
        $this->assertNull($result->serviceCatalog);
    }

    public function test_resolve_coverage_service_unavailable_when_rule_missing(): void
    {
        $postalCode = PostalCode::factory()->create(['code' => '7005']);
        $zone = ServiceZone::factory()->create([
            'status' => 'active',
            'is_visible' => true,
            'is_bookable' => true,
        ]);
        $this->attach($zone, $postalCode);
        $catalog = ServiceCatalog::factory()->create(['code' => 'SVC-NORULE', 'is_active' => true]);

        $result = $this->service()->resolveCoverage('7005', null, 'SVC-NORULE');

        $this->assertSame('service_unavailable', $result->status);
        $this->assertSame($catalog->id, $result->serviceCatalog?->id);
        $this->assertNull($result->zoneServiceRule);
    }

    public function test_resolve_coverage_manual_validation_from_rule(): void
    {
        $postalCode = PostalCode::factory()->create(['code' => '7006']);
        $zone = ServiceZone::factory()->create([
            'status' => 'active',
            'is_visible' => true,
            'is_bookable' => true,
        ]);
        $this->attach($zone, $postalCode);
        $catalog = ServiceCatalog::factory()->create(['code' => 'SVC-MANUAL', 'is_active' => true]);
        $this->makeRule([
            'service_zone_id' => $zone->id,
            'service_catalog_id' => $catalog->id,
            'is_enabled' => true,
            'requires_manual_validation' => true,
        ]);

        $result = $this->service()->resolveCoverage('7006', null, 'SVC-MANUAL');

        $this->assertSame('manual_validation', $result->status);
        $this->assertTrue($result->isBookable());
        $this->assertTrue($result->requiresManualValidation());
    }

    public function test_resolve_coverage_covered_with_service(): void
    {
        $postalCode = PostalCode::factory()->create(['code' => '7007']);
        $zone = ServiceZone::factory()->create([
            'status' => 'active',
            'is_visible' => true,
            'is_bookable' => true,
            'name' => 'Mons Zone Test',
            'metadata' => null,
        ]);
        $this->attach($zone, $postalCode);
        $catalog = ServiceCatalog::factory()->create([
            'code' => 'SVC-OK',
            'is_active' => true,
            'requires_manual_validation' => false,
        ]);
        $this->makeRule([
            'service_zone_id' => $zone->id,
            'service_catalog_id' => $catalog->id,
            'is_enabled' => true,
            'requires_manual_validation' => false,
        ]);

        $result = $this->service()->resolveCoverage('7007', null, 'SVC-OK');

        $this->assertSame('covered', $result->status);
        $this->assertTrue($result->isBookable());
        $this->assertFalse($result->requiresManualValidation());
        $this->assertStringContainsString('Mons Zone Test', $result->message);
        $this->assertSame('postal_code', $result->resolutionSource);
    }

    public function test_resolve_coverage_covered_without_service_identifier(): void
    {
        $postalCode = PostalCode::factory()->create(['code' => '7008']);
        $zone = ServiceZone::factory()->create([
            'status' => 'active',
            'is_visible' => true,
            'is_bookable' => true,
        ]);
        $this->attach($zone, $postalCode);

        $result = $this->service()->resolveCoverage('7008', null, null);

        $this->assertSame('covered', $result->status);
        $this->assertNull($result->serviceCatalog);
    }

    public function test_resolve_coverage_manual_validation_from_zone_metadata(): void
    {
        $postalCode = PostalCode::factory()->create(['code' => '7009']);
        $zone = ServiceZone::factory()->create([
            'status' => 'active',
            'is_visible' => true,
            'is_bookable' => true,
            'metadata' => ['requires_manual_validation' => true],
        ]);
        $this->attach($zone, $postalCode);

        $result = $this->service()->resolveCoverage('7009', null, null);

        $this->assertSame('manual_validation', $result->status);
    }

    public function test_resolve_coverage_falls_back_to_site_postal_code(): void
    {
        $postalCode = PostalCode::factory()->create(['code' => '7010']);
        $zone = ServiceZone::factory()->create([
            'status' => 'active',
            'is_visible' => true,
            'is_bookable' => true,
        ]);
        $this->attach($zone, $postalCode);

        $site = OrganizationSite::factory()->create([
            'service_zone_id' => null,
            'postal_code_id' => $postalCode->id,
        ]);

        // No code passed in -> resolver returns null -> fall back to site postal reference.
        $result = $this->service()->resolveCoverage(null, null, null, $site);

        $this->assertSame('covered', $result->status);
        $this->assertSame($postalCode->id, $result->postalCode?->id);
        $this->assertSame('postal_code', $result->resolutionSource);
    }
}

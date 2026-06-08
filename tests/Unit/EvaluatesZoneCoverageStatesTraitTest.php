<?php

namespace Tests\Unit;

use App\Data\ZoneCoverageResult;
use App\Models\OrganizationSite;
use App\Models\PostalCode;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\ZoneServiceRule;
use App\Services\Booking\Concerns\EvaluatesZoneCoverageStates;
use Tests\TestCase;

/**
 * In-file host that uses the trait under test and exposes deterministic
 * stubs for the four collaborator methods the trait depends on, so that
 * every branch of resolveCoverage() can be driven without touching the DB.
 */
class CoverageStateEvaluatorHost
{
    use EvaluatesZoneCoverageStates;

    public ?PostalCode $postalCodeReturn = null;

    public array $zoneResolutionReturn = ['zone' => null, 'source' => null];

    public ?ServiceCatalog $catalogReturn = null;

    public ?ZoneServiceRule $ruleReturn = null;

    public function resolvePostalCode(?string $code, ?string $city = null): ?PostalCode
    {
        return $this->postalCodeReturn;
    }

    public function resolveServiceZoneWithSource(?PostalCode $postalCode, ?OrganizationSite $selectedSite = null, bool $bookableOnly = false): array
    {
        return $this->zoneResolutionReturn;
    }

    public function resolveServiceCatalog(?string $serviceType, ?ServiceZone $zone = null): ?ServiceCatalog
    {
        return $this->catalogReturn;
    }

    public function resolveZoneServiceRule(?ServiceZone $zone, ?ServiceCatalog $catalog): ?ZoneServiceRule
    {
        return $this->ruleReturn;
    }
}

class EvaluatesZoneCoverageStatesTraitTest extends TestCase
{
    private function host(): CoverageStateEvaluatorHost
    {
        return new CoverageStateEvaluatorHost;
    }

    private function postalCode(): PostalCode
    {
        $pc = new PostalCode;
        $pc->code = '1000';

        return $pc;
    }

    private function zone(string $status = 'active', bool $visible = true, bool $bookable = true): ServiceZone
    {
        $zone = new ServiceZone;
        $zone->status = $status;
        $zone->is_visible = $visible;
        $zone->is_bookable = $bookable;

        return $zone;
    }

    private function rule(bool $manual = false): ZoneServiceRule
    {
        $rule = new ZoneServiceRule;
        $rule->requires_manual_validation = $manual;

        return $rule;
    }

    public function test_invalid_postal_code_when_nothing_resolves(): void
    {
        $host = $this->host();
        $host->postalCodeReturn = null;
        $host->zoneResolutionReturn = ['zone' => null, 'source' => null];

        $result = $host->resolveCoverage('0000', 'Nowhere', null);

        $this->assertInstanceOf(ZoneCoverageResult::class, $result);
        $this->assertSame('invalid_postal_code', $result->status);
        $this->assertNull($result->postalCode);
        $this->assertNull($result->zone);
        $this->assertFalse($result->isBookable());
    }

    public function test_postal_code_falls_back_to_selected_site_reference(): void
    {
        $host = $this->host();
        $host->postalCodeReturn = null;
        $host->zoneResolutionReturn = ['zone' => $this->zone(), 'source' => 'organization_site'];

        $sitePostal = $this->postalCode();
        $site = new OrganizationSite;
        $site->postal_code_id = 99;
        // Pre-load the relation so loadMissing() is a no-op and no DB query fires.
        $site->setRelation('postalCodeReference', $sitePostal);

        $result = $host->resolveCoverage(null, null, null, $site);

        // postalCode came from the site, so we should not get invalid_postal_code.
        $this->assertSame('covered', $result->status);
        $this->assertSame($sitePostal, $result->postalCode);
        $this->assertSame('organization_site', $result->resolutionSource);
    }

    public function test_unsupported_when_postal_code_resolves_but_no_zone(): void
    {
        $host = $this->host();
        $host->postalCodeReturn = $this->postalCode();
        $host->zoneResolutionReturn = ['zone' => null, 'source' => 'national_fallback'];

        $result = $host->resolveCoverage('1000', null, null);

        $this->assertSame('unsupported', $result->status);
        $this->assertNotNull($result->postalCode);
        $this->assertNull($result->zone);
        $this->assertSame('national_fallback', $result->resolutionSource);
    }

    public function test_paused_zone(): void
    {
        $host = $this->host();
        $host->postalCodeReturn = $this->postalCode();
        $host->zoneResolutionReturn = ['zone' => $this->zone(status: 'paused'), 'source' => 'postal_code'];

        $result = $host->resolveCoverage('1000', null, null);

        $this->assertSame('paused', $result->status);
        $this->assertFalse($result->isBookable());
    }

    public function test_hidden_zone_when_not_visible_and_not_bookable(): void
    {
        $host = $this->host();
        $host->postalCodeReturn = $this->postalCode();
        $host->zoneResolutionReturn = ['zone' => $this->zone(visible: false, bookable: false), 'source' => 'postal_code'];

        $result = $host->resolveCoverage('1000', null, null);

        $this->assertSame('hidden', $result->status);
    }

    public function test_non_bookable_zone_when_visible_but_not_bookable(): void
    {
        $host = $this->host();
        $host->postalCodeReturn = $this->postalCode();
        $host->zoneResolutionReturn = ['zone' => $this->zone(visible: true, bookable: false), 'source' => 'postal_code'];

        $result = $host->resolveCoverage('1000', null, null);

        $this->assertSame('non_bookable', $result->status);
    }

    public function test_service_unavailable_when_service_requested_but_no_catalog(): void
    {
        $host = $this->host();
        $host->postalCodeReturn = $this->postalCode();
        $host->zoneResolutionReturn = ['zone' => $this->zone(), 'source' => 'postal_code'];
        $host->catalogReturn = null;

        $result = $host->resolveCoverage('1000', null, 'cleaning');

        $this->assertSame('service_unavailable', $result->status);
        $this->assertNull($result->serviceCatalog);
    }

    public function test_service_unavailable_when_catalog_exists_but_no_rule(): void
    {
        $host = $this->host();
        $host->postalCodeReturn = $this->postalCode();
        $host->zoneResolutionReturn = ['zone' => $this->zone(), 'source' => 'postal_code'];
        $host->catalogReturn = new ServiceCatalog;
        $host->ruleReturn = null;

        $result = $host->resolveCoverage('1000', null, 'cleaning');

        $this->assertSame('service_unavailable', $result->status);
        $this->assertNotNull($result->serviceCatalog);
        $this->assertNull($result->zoneServiceRule);
    }

    public function test_covered_when_service_rule_present_without_manual_validation(): void
    {
        $host = $this->host();
        $host->postalCodeReturn = $this->postalCode();
        $host->zoneResolutionReturn = ['zone' => $this->zone(), 'source' => 'postal_code'];
        $host->catalogReturn = new ServiceCatalog;
        $host->ruleReturn = $this->rule(manual: false);

        $result = $host->resolveCoverage('1000', null, 'cleaning');

        $this->assertSame('covered', $result->status);
        $this->assertTrue($result->isBookable());
        $this->assertFalse($result->requiresManualValidation());
        $this->assertNotNull($result->zoneServiceRule);
    }

    public function test_manual_validation_when_rule_requires_it(): void
    {
        $host = $this->host();
        $host->postalCodeReturn = $this->postalCode();
        $host->zoneResolutionReturn = ['zone' => $this->zone(), 'source' => 'postal_code'];
        $host->catalogReturn = new ServiceCatalog;
        $host->ruleReturn = $this->rule(manual: true);

        $result = $host->resolveCoverage('1000', null, 'cleaning');

        $this->assertSame('manual_validation', $result->status);
        $this->assertTrue($result->isBookable());
        $this->assertTrue($result->requiresManualValidation());
    }

    public function test_covered_without_service_request_skips_service_guards(): void
    {
        $host = $this->host();
        $host->postalCodeReturn = $this->postalCode();
        $host->zoneResolutionReturn = ['zone' => $this->zone(), 'source' => 'postal_code'];
        // No service type passed: catalog/rule null, but guards are skipped.
        $host->catalogReturn = null;
        $host->ruleReturn = null;

        $result = $host->resolveCoverage('1000', null, null);

        $this->assertSame('covered', $result->status);
        $this->assertNull($result->zoneServiceRule);
    }
}

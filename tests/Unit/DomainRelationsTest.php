<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

class DomainRelationsTest extends TestCase
{
    use CreatesDomainFixtures;
    use RefreshDatabase;

    public function test_geo_hierarchy_relations_are_wired_correctly(): void
    {
        $context = $this->createGeoContext();

        $this->assertTrue($context['country']->regions->contains($context['region']));
        $this->assertTrue($context['region']->provinces->contains($context['province']));
        $this->assertTrue($context['province']->communes->contains($context['commune']));
        $this->assertTrue($context['commune']->postalCodes->contains($context['postalCode']));
        $this->assertSame($context['country']->id, $context['postalCode']->country->id);
    }

    public function test_service_zone_service_rule_and_employee_relations_are_wired_correctly(): void
    {
        $context = $this->createZoneContext();
        $employe = User::factory()->employe()->create(['primary_service_zone_id' => $context['zone']->id]);

        $assignment = $this->assignEmployeToZone($employe, $context['zone']);

        $this->assertTrue($context['zone']->postalCodes->contains($context['postalCode']));
        $this->assertTrue($context['zone']->serviceCatalogs->contains($context['service']));
        $this->assertTrue($context['service']->serviceZones->contains($context['zone']));
        $this->assertSame($context['zone']->id, $assignment->serviceZone->id);
        $this->assertTrue($employe->serviceZones->contains($context['zone']));
    }

    public function test_organization_account_site_and_user_relations_are_wired_correctly(): void
    {
        $client = User::factory()->client()->create();
        $context = $this->createEntrepriseContext($client);

        $client->update(['organization_account_id' => $context['account']->id]);

        $this->assertTrue($context['account']->sites->contains($context['site']));
        $this->assertTrue($context['account']->users->contains($client->fresh()));
        $this->assertSame($context['zone']->id, $context['site']->serviceZone->id);
        $this->assertSame($context['postalCode']->id, $context['site']->postalCodeReference->id);
        $this->assertTrue($client->fresh()->organizationSites->contains($context['site']));
    }
}

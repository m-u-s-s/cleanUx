<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

class ServiceZoneRelationsTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    public function test_service_zone_exposes_postal_codes_services_and_employees_relationships(): void
    {
        $context = $this->createCoverageContext();
        $employee = User::factory()->employe()->create();
        $this->assignEmployeeToZone($employee, $context['zone']);

        $zone = $context['zone']->fresh(['postalCodes', 'serviceCatalogs', 'employees']);

        $this->assertTrue($zone->postalCodes->contains('id', $context['postalCode']->id));
        $this->assertTrue($zone->serviceCatalogs->contains('id', $context['service']->id));
        $this->assertTrue($zone->employees->contains('id', $employee->id));
    }
}

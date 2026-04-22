<?php

namespace Tests\Feature;

use App\Livewire\Client\PrendreRendezVous;
use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

class ZoneAwareReservationTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    public function test_standard_client_can_create_zone_aware_booking_with_auto_assigned_employee(): void
    {
        $context = $this->createCoverageContext();
        $client = User::factory()->client()->create();
        $employee = User::factory()->employe()->create();

        $bookingDate = now()->addDays(2)->toDateString();
        $this->assignEmployeeToZone($employee, $context['zone'], [], ['date' => $bookingDate]);

        $this->actingAs($client);

        Livewire::test(PrendreRendezVous::class)
            ->set('selected_service_identifier', $context['service']->code ?: $context['service']->slug)
            ->set('type_lieu', 'appartement')
            ->set('frequence', 'ponctuel')
            ->set('surface', 'moins_50')
            ->set('adresse', 'Rue de la Loi 1')
            ->set('ville', $context['postalCode']->city_name)
            ->set('postal_code_input', $context['postalCode']->code)
            ->set('telephone_client', '0470000000')
            ->set('priorite', 'normale')
            ->set('rdvDate', $bookingDate)
            ->set('rdvHeure', '10:00')
            ->call('validerRdv')
            ->assertHasNoErrors()
            ->assertSet('step', 5)
            ->assertSet('createdEmployeName', $employee->name);

        $this->assertDatabaseHas('rendez_vous', [
            'client_id' => $client->id,
            'employe_id' => $employee->id,
            'service_zone_id' => $context['zone']->id,
            'service_catalog_id' => $context['service']->id,
            'postal_code_id' => $context['postalCode']->id,
            'status' => 'en_attente',
        ]);
    }

    public function test_premium_client_cannot_book_employee_outside_covered_zone(): void
    {
        $primaryContext = $this->createCoverageContext();
        $otherContext = $this->createCoverageContext([
            'region_code' => 'WAL',
            'region_name' => 'Wallonie',
            'region_slug' => 'wallonie',
            'province_code' => 'WBR',
            'province_name' => 'Brabant wallon',
            'province_slug' => 'brabant-wallon',
            'commune_name' => 'Wavre',
            'commune_slug' => 'wavre',
            'nis_code' => '25112',
            'postal_code' => '1300',
            'city_name' => 'Wavre',
            'zone' => ['code' => 'zone-wavre', 'name' => 'Zone Wavre', 'slug' => 'zone-wavre'],
            'service' => ['code' => 'svc-wavre', 'name' => 'Service Wavre', 'slug' => 'service-wavre'],
        ]);

        $client = User::factory()->premiumClient()->create();
        $employee = User::factory()->employe()->create();
        $bookingDate = now()->addDays(2)->toDateString();

        $this->assignEmployeeToZone($employee, $otherContext['zone'], [], ['date' => $bookingDate]);

        $this->actingAs($client);

        Livewire::test(PrendreRendezVous::class)
            ->set('selected_service_identifier', $primaryContext['service']->code ?: $primaryContext['service']->slug)
            ->set('type_lieu', 'appartement')
            ->set('frequence', 'ponctuel')
            ->set('surface', 'moins_50')
            ->set('adresse', 'Rue de la Loi 1')
            ->set('ville', $primaryContext['postalCode']->city_name)
            ->set('postal_code_input', $primaryContext['postalCode']->code)
            ->set('telephone_client', '0470000000')
            ->set('priorite', 'normale')
            ->set('employe_id', $employee->id)
            ->set('rdvDate', $bookingDate)
            ->set('rdvHeure', '10:00')
            ->call('validerRdv')
            ->assertHasErrors(['employe_id']);

        $this->assertDatabaseCount('rendez_vous', 0);
    }

    public function test_service_capacity_limit_blocks_new_booking_in_same_zone(): void
    {
        $context = $this->createCoverageContext([
            'rule' => ['maximum_daily_capacity' => 1],
        ]);

        $client = User::factory()->client()->create();
        $employee = User::factory()->employe()->create();
        $bookingDate = now()->addDays(2)->toDateString();
        $this->assignEmployeeToZone($employee, $context['zone'], [], ['date' => $bookingDate]);

        RendezVous::factory()->create([
            'client_id' => User::factory()->client()->create()->id,
            'employe_id' => $employee->id,
            'service_catalog_id' => $context['service']->id,
            'service_zone_id' => $context['zone']->id,
            'postal_code_id' => $context['postalCode']->id,
            'date' => $bookingDate,
            'heure' => '08:00:00',
            'status' => 'confirme',
        ]);

        $this->actingAs($client);

        Livewire::test(PrendreRendezVous::class)
            ->set('selected_service_identifier', $context['service']->code ?: $context['service']->slug)
            ->set('type_lieu', 'appartement')
            ->set('frequence', 'ponctuel')
            ->set('surface', 'moins_50')
            ->set('adresse', 'Rue de la Loi 1')
            ->set('ville', $context['postalCode']->city_name)
            ->set('postal_code_input', $context['postalCode']->code)
            ->set('telephone_client', '0470000000')
            ->set('priorite', 'normale')
            ->set('rdvDate', $bookingDate)
            ->set('rdvHeure', '10:00')
            ->call('validerRdv')
            ->assertHasErrors(['selected_service_identifier']);

        $this->assertDatabaseCount('rendez_vous', 1);
    }
}

<?php

namespace Tests\Feature;

use App\Livewire\AdminDashboard;
use App\Livewire\ClientDashboard;
use App\Livewire\EmployeDashboard;
use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

class PortalZoneAwareDashboardsTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    public function test_client_dashboard_filters_favorites_and_services_by_zone(): void
    {
        $brussels = $this->createCoverageContext([
            'service' => [
                'name' => 'Nettoyage Bruxelles',
                'code' => 'SVC-BRU-CLIENT',
                'slug' => 'nettoyage-bruxelles-client',
                'service_type' => 'nettoyage_standard',
                'is_entreprise' => false,
            ],
        ]);

        $wavre = $this->createCoverageContext([
            'country_iso' => 'WA',
            'country_iso3' => 'WAV',
            'country_name' => 'Pays Wavre',
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
            'zone' => ['code' => 'zone-wavre-client', 'name' => 'Zone Wavre Client', 'slug' => 'zone-wavre-client'],
            'service' => [
                'name' => 'Nettoyage Wavre',
                'code' => 'SVC-WAV-CLIENT',
                'slug' => 'nettoyage-wavre-client',
                'service_type' => 'nettoyage_wavre',
                'is_entreprise' => false,
            ],
        ]);

        $client = User::factory()->premiumClient()->create([
            'primary_service_zone_id' => $brussels['zone']->id,
            'postal_code_id' => $brussels['postalCode']->id,
        ]);

        $employeeBrussels = User::factory()->employe()->create(['name' => 'Employé Bruxelles']);
        $employeeWavre = User::factory()->employe()->create(['name' => 'Employé Wavre']);

        $this->assignEmployeeToZone($employeeBrussels, $brussels['zone']);
        $this->assignEmployeeToZone($employeeWavre, $wavre['zone']);

        $client->favoriteEmployes()->attach($employeeBrussels->id, ['is_favorite' => true]);
        $client->favoriteEmployes()->attach($employeeWavre->id, ['is_favorite' => true]);

        $this->actingAs($client);

        Livewire::test(ClientDashboard::class)
            ->assertSee('Nettoyage Bruxelles')
            ->assertDontSee('Nettoyage Wavre')
            ->assertSee('Employé Bruxelles')
            ->assertDontSee('Employé Wavre');
    }

    public function test_employee_dashboard_flags_out_of_zone_missions(): void
    {
        $brussels = $this->createCoverageContext([
            'service' => [
                'name' => 'Mission Bruxelles Employé',
                'code' => 'SVC-BRU-EMP',
                'slug' => 'mission-bruxelles-employe',
            ],
        ]);

        $wavre = $this->createCoverageContext([
            'country_iso' => 'WC',
            'country_iso3' => 'WCE',
            'country_name' => 'Pays WC',
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
            'zone' => ['code' => 'zone-wavre-emp', 'name' => 'Zone Wavre Employé', 'slug' => 'zone-wavre-emp'],
            'service' => [
                'name' => 'Mission Wavre Employé',
                'code' => 'SVC-WAV-EMP',
                'slug' => 'mission-wavre-employe',
            ],
        ]);

        $employee = User::factory()->employe()->create([
            'name' => 'Technicien Test',
            'primary_service_zone_id' => $brussels['zone']->id,
        ]);

        $this->assignEmployeeToZone($employee, $brussels['zone']);

        RendezVous::factory()->create([
            'client_id' => User::factory()->client()->create()->id,
            'employe_id' => $employee->id,
            'service_catalog_id' => $wavre['service']->id,
            'service_zone_id' => $wavre['zone']->id,
            'postal_code_id' => $wavre['postalCode']->id,
            'date' => today()->toDateString(),
            'heure' => '10:00:00',
            'status' => 'confirme',
            'ville' => $wavre['postalCode']->city_name,
            'code_postal' => $wavre['postalCode']->code,
        ]);

        $this->actingAs($employee);

        Livewire::test(EmployeDashboard::class)
            ->assertSee('Mission(s) hors zone')
            ->assertSee('Zone Wavre Employé');
    }

    public function test_zone_scoped_admin_dashboard_only_surfaces_managed_zone_data(): void
    {
        $brussels = $this->createCoverageContext([
            'service' => [
                'name' => 'Service Admin Bruxelles',
                'code' => 'SVC-BRU-ADMIN',
                'slug' => 'service-admin-bruxelles',
            ],
        ]);

        $wavre = $this->createCoverageContext([
            'country_iso' => 'WD',
            'country_iso3' => 'WDE',
            'country_name' => 'Pays WD',
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
            'zone' => ['code' => 'zone-wavre-admin', 'name' => 'Zone Wavre Admin', 'slug' => 'zone-wavre-admin'],
            'service' => [
                'name' => 'Service Admin Wavre',
                'code' => 'SVC-WAV-ADMIN',
                'slug' => 'service-admin-wavre',
            ],
        ]);

        $admin = User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ZONE,
            'managed_service_zone_id' => $brussels['zone']->id,
        ]);

        $clientBrussels = User::factory()->client()->create(['name' => 'Client Bruxelles']);
        $clientWavre = User::factory()->client()->create(['name' => 'Client Wavre']);

        RendezVous::factory()->create([
            'client_id' => $clientBrussels->id,
            'employe_id' => null,
            'service_catalog_id' => $brussels['service']->id,
            'service_zone_id' => $brussels['zone']->id,
            'postal_code_id' => $brussels['postalCode']->id,
            'date' => now()->addDay()->toDateString(),
            'heure' => '09:00:00',
            'status' => 'en_attente',
            'ville' => $brussels['postalCode']->city_name,
            'code_postal' => $brussels['postalCode']->code,
        ]);

        RendezVous::factory()->create([
            'client_id' => $clientWavre->id,
            'employe_id' => null,
            'service_catalog_id' => $wavre['service']->id,
            'service_zone_id' => $wavre['zone']->id,
            'postal_code_id' => $wavre['postalCode']->id,
            'date' => now()->addDay()->toDateString(),
            'heure' => '11:00:00',
            'status' => 'en_attente',
            'ville' => $wavre['postalCode']->city_name,
            'code_postal' => $wavre['postalCode']->code,
        ]);

        $this->actingAs($admin);

        Livewire::test(AdminDashboard::class)
            ->assertSet('filtreZone', (string) $brussels['zone']->id)
            ->assertSee('Client Bruxelles')
            ->assertDontSee('Client Wavre')
            ->assertDontSee($wavre['zone']->name)
            ->assertDontSee('Zone gérée');
    }
}

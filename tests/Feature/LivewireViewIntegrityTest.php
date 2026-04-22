<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMissionPortalFixtures;
use Tests\TestCase;

class LivewireViewIntegrityTest extends TestCase
{
    use CreatesMissionPortalFixtures;
    use RefreshDatabase;

    public function test_admin_countries_route_renders(): void
    {
        $admin = User::factory()->admin()->create([
            'access_scope' => 'all',
            'permissions' => [],
            'is_active' => true,
        ]);

        Country::create([
            'iso_code' => 'BE',
            'iso3_code' => 'BEL',
            'name' => 'Belgique',
            'official_name' => 'Royaume de Belgique',
            'default_locale' => 'fr_BE',
            'currency_code' => 'EUR',
            'phone_code' => '+32',
            'timezone' => 'Europe/Brussels',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.countries'))
            ->assertOk()
            ->assertSee('Pilotage des pays');
    }

    public function test_client_recurring_edit_route_renders(): void
    {
        $scenario = $this->createRecurringPortalContext();

        $this->actingAs($scenario['client'])
            ->get(route('client.rendezvous.series.edit', $scenario['rendezVous']))
            ->assertOk()
            ->assertSee('Gérer ma série récurrente');
    }

    public function test_admin_recurring_edit_route_renders(): void
    {
        $scenario = $this->createRecurringPortalContext();
        $admin = User::factory()->admin()->create([
            'access_scope' => 'all',
            'permissions' => [],
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.rendezvous.series.edit', $scenario['rendezVous']))
            ->assertOk()
            ->assertSee('Gérer une série récurrente');
    }

    public function test_employe_missions_route_renders(): void
    {
        $scenario = $this->createMissionPortalContext([
            'status' => 'assigned',
        ]);

        $this->actingAs($scenario['employee'])
            ->get(route('employe.missions'))
            ->assertOk()
            ->assertSee('Mes missions');
    }
}

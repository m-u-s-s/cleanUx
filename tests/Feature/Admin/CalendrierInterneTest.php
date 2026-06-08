<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\CalendrierInterne;
use App\Models\Booking;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Coverage for the CalendrierInterne admin Livewire component: mount defaults,
 * computed properties (zones/services/employes/stats/calendarEvents/upcoming)
 * and the filter / preset actions.
 */
class CalendrierInterneTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin(): User
    {
        return User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'is_active' => true,
        ]);
    }

    protected function seedZone(): ServiceZone
    {
        return ServiceZone::factory()->create([
            'coverage_type' => 'province',
            'status' => 'active',
            'is_bookable' => true,
            'is_visible' => true,
        ]);
    }

    public function test_mount_sets_default_month_window_and_renders(): void
    {
        $admin = $this->createAdmin();

        Livewire::actingAs($admin)
            ->test(CalendrierInterne::class)
            ->assertOk()
            ->assertSet('viewMode', 'dayGridMonth')
            ->assertSet('dateFrom', now()->startOfMonth()->toDateString())
            ->assertSet('dateTo', now()->endOfMonth()->toDateString());
    }

    public function test_computed_properties_expose_stats_events_and_upcoming(): void
    {
        $admin = $this->createAdmin();
        $zone = $this->seedZone();
        $today = today()->toDateString();

        Booking::factory()->create(['service_zone_id' => $zone->id, 'status' => 'en_attente', 'date' => $today]);
        Booking::factory()->confirme()->create(['service_zone_id' => $zone->id, 'date' => $today]);
        Booking::factory()->termine()->create(['service_zone_id' => $zone->id, 'date' => $today]);
        Booking::factory()->create(['service_zone_id' => $zone->id, 'status' => 'en_route', 'date' => $today]);
        Booking::factory()->refuse()->create(['service_zone_id' => $zone->id, 'date' => $today]);

        $component = Livewire::actingAs($admin)
            ->test(CalendrierInterne::class)
            ->assertOk();

        $stats = $component->get('stats');
        $this->assertSame(5, $stats['total']);
        $this->assertSame(1, $stats['en_attente']);
        $this->assertSame(1, $stats['confirme']);
        $this->assertSame(1, $stats['terrain']);
        $this->assertSame(1, $stats['termine']);

        $events = $component->get('calendarEvents');
        $this->assertCount(5, $events);
        $this->assertArrayHasKey('backgroundColor', $events[0]);
        $this->assertArrayHasKey('extendedProps', $events[0]);

        $upcoming = $component->get('upcoming');
        $this->assertCount(5, $upcoming);

        $this->assertCount(1, $component->get('zones'));
        $this->assertGreaterThanOrEqual(1, count($component->get('employes')));
    }

    public function test_status_filter_narrows_results(): void
    {
        $admin = $this->createAdmin();
        $zone = $this->seedZone();
        $today = today()->toDateString();

        Booking::factory()->create(['service_zone_id' => $zone->id, 'status' => 'en_attente', 'date' => $today]);
        Booking::factory()->confirme()->create(['service_zone_id' => $zone->id, 'date' => $today]);

        $component = Livewire::actingAs($admin)
            ->test(CalendrierInterne::class)
            ->set('status', 'confirme')
            ->assertOk();

        $this->assertSame(1, $component->get('stats')['total']);
    }

    public function test_search_filter_uses_structured_scope(): void
    {
        $admin = $this->createAdmin();
        $zone = $this->seedZone();
        $today = today()->toDateString();

        $match = Booking::factory()->create([
            'service_zone_id' => $zone->id,
            'status' => 'en_attente',
            'date' => $today,
            'booking_reference' => 'CUX-UNIQUE-REF-001',
        ]);
        Booking::factory()->create(['service_zone_id' => $zone->id, 'status' => 'en_attente', 'date' => $today]);

        $component = Livewire::actingAs($admin)
            ->test(CalendrierInterne::class)
            ->set('search', 'CUX-UNIQUE-REF-001')
            ->assertOk();

        $this->assertSame(1, $component->get('stats')['total']);
        $this->assertSame($match->id, $component->get('upcoming')->first()->id);
    }

    public function test_set_preset_today_week_and_month(): void
    {
        $admin = $this->createAdmin();

        Livewire::actingAs($admin)
            ->test(CalendrierInterne::class)
            ->call('setPreset', 'today')
            ->assertSet('dateFrom', today()->toDateString())
            ->assertSet('dateTo', today()->toDateString())
            ->call('setPreset', 'week')
            ->assertSet('dateFrom', now()->startOfWeek()->toDateString())
            ->assertSet('dateTo', now()->endOfWeek()->toDateString())
            ->call('setPreset', 'month')
            ->assertSet('dateFrom', now()->startOfMonth()->toDateString())
            ->assertSet('dateTo', now()->endOfMonth()->toDateString())
            ->call('setPreset', 'unknown')
            ->assertOk();
    }

    public function test_reset_filters_restores_defaults(): void
    {
        $admin = $this->createAdmin();

        Livewire::actingAs($admin)
            ->test(CalendrierInterne::class)
            ->set('status', 'confirme')
            ->set('search', 'foo')
            ->set('viewMode', 'timeGridWeek')
            ->call('resetFilters')
            ->assertSet('status', '')
            ->assertSet('search', '')
            ->assertSet('viewMode', 'dayGridMonth')
            ->assertSet('dateFrom', now()->startOfMonth()->toDateString())
            ->assertSet('dateTo', now()->endOfMonth()->toDateString());
    }
}

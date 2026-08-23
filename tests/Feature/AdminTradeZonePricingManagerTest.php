<?php

namespace Tests\Feature;

use App\Livewire\Admin\TradeZonePricingManager;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Tests for the TradeZonePricingManager Livewire component. */
class AdminTradeZonePricingManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin(): User
    {
        return User::factory()->admin()->create([
            'permissions' => ['manage-services', 'perform-critical-admin-actions'],
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_view_empty_zone_pricing_list(): void
    {
        $admin = $this->createAdmin();
        $trade = Trade::factory()->create(['name' => 'Nettoyage Test']);

        $this->actingAs($admin);

        Livewire::test(TradeZonePricingManager::class, ['trade' => $trade])
            ->assertSee('Nettoyage Test')
            ->assertSee('Tarification par zone');
    }

    public function test_admin_can_add_a_zone_to_a_trade(): void
    {
        $admin = $this->createAdmin();
        $trade = Trade::factory()->create(['default_hourly_rate' => 45.00]);
        $zone = ServiceZone::factory()->create(['name' => 'Bruxelles']);

        $this->actingAs($admin);

        Livewire::test(TradeZonePricingManager::class, ['trade' => $trade])
            ->call('addZone', $zone->id)
            ->assertSee('Zone ajoutée');

        $this->assertDatabaseHas('trade_zone_pricing', [
            'trade_id' => $trade->id,
            'service_zone_id' => $zone->id,
            'base_rate_cents' => 4500,
            'is_active' => true,
        ]);
    }

    public function test_admin_cannot_add_same_zone_twice(): void
    {
        $admin = $this->createAdmin();
        $trade = Trade::factory()->create();
        $zone = ServiceZone::factory()->create();

        TradeZonePricing::factory()->create([
            'trade_id' => $trade->id,
            'service_zone_id' => $zone->id,
        ]);

        $this->actingAs($admin);

        Livewire::test(TradeZonePricingManager::class, ['trade' => $trade])
            ->call('addZone', $zone->id)
            ->assertSee('déjà configurée');
    }

    public function test_admin_can_edit_zone_pricing(): void
    {
        $admin = $this->createAdmin();
        $trade = Trade::factory()->create();
        $zone = ServiceZone::factory()->create();
        $pricing = TradeZonePricing::factory()->create([
            'trade_id' => $trade->id,
            'service_zone_id' => $zone->id,
            'base_rate_cents' => 3000,
        ]);

        $this->actingAs($admin);

        Livewire::test(TradeZonePricingManager::class, ['trade' => $trade])
            ->call('edit', $pricing->id)
            ->assertSet('editingId', $pricing->id)
            ->assertSet('form_base_rate_cents', '3000');
    }

    public function test_admin_can_save_edited_zone_pricing(): void
    {
        $admin = $this->createAdmin();
        $trade = Trade::factory()->create();
        $zone = ServiceZone::factory()->create();
        $pricing = TradeZonePricing::factory()->create([
            'trade_id' => $trade->id,
            'service_zone_id' => $zone->id,
            'base_rate_cents' => 3000,
        ]);

        $this->actingAs($admin);

        Livewire::test(TradeZonePricingManager::class, ['trade' => $trade])
            ->call('edit', $pricing->id)
            ->set('form_base_rate_cents', '5500')
            ->set('form_surge_multiplier', '1.25')
            ->set('form_min_price_cents', '2000')
            ->set('form_max_price_cents', '20000')
            ->set('form_is_active', true)
            ->call('save')
            ->assertSet('editingId', null);

        $this->assertDatabaseHas('trade_zone_pricing', [
            'id' => $pricing->id,
            'base_rate_cents' => 5500,
            'min_price_cents' => 2000,
            'max_price_cents' => 20000,
        ]);
    }

    public function test_save_validates_base_rate_non_negative(): void
    {
        $admin = $this->createAdmin();
        $trade = Trade::factory()->create();
        $zone = ServiceZone::factory()->create();
        $pricing = TradeZonePricing::factory()->create([
            'trade_id' => $trade->id,
            'service_zone_id' => $zone->id,
        ]);

        $this->actingAs($admin);

        Livewire::test(TradeZonePricingManager::class, ['trade' => $trade])
            ->call('edit', $pricing->id)
            ->set('form_base_rate_cents', '-1')
            ->call('save')
            ->assertHasErrors(['form_base_rate_cents']);
    }

    public function test_admin_can_toggle_active(): void
    {
        $admin = $this->createAdmin();
        $trade = Trade::factory()->create();
        $zone = ServiceZone::factory()->create();
        $pricing = TradeZonePricing::factory()->create([
            'trade_id' => $trade->id,
            'service_zone_id' => $zone->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        Livewire::test(TradeZonePricingManager::class, ['trade' => $trade])
            ->call('toggleActive', $pricing->id);

        $this->assertDatabaseHas('trade_zone_pricing', [
            'id' => $pricing->id,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_delete_zone_pricing(): void
    {
        $admin = $this->createAdmin();
        $trade = Trade::factory()->create();
        $zone = ServiceZone::factory()->create();
        $pricing = TradeZonePricing::factory()->create([
            'trade_id' => $trade->id,
            'service_zone_id' => $zone->id,
        ]);

        $this->actingAs($admin);

        Livewire::test(TradeZonePricingManager::class, ['trade' => $trade])
            ->call('delete', $pricing->id);

        $this->assertDatabaseMissing('trade_zone_pricing', ['id' => $pricing->id]);
    }

    public function test_route_resolves_for_admin(): void
    {
        $admin = $this->createAdmin();
        $trade = Trade::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.trades.pricing', $trade))
            ->assertOk();
    }
}

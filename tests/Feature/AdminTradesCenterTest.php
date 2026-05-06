<?php

namespace Tests\Feature;

use App\Livewire\Admin\Trades as AdminTradesComponent;
use App\Models\ServiceCatalog;
use App\Models\Trade;
use App\Models\User;
use Database\Seeders\TradeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 1 — Tests Livewire admin pour la gestion des Trades.
 *
 * Calque sur le pattern de AdminGestionZonesTest:
 *   User::factory()->admin()->create([...permissions, access_scope, is_active])
 *   $this->actingAs($admin)
 *   Livewire::test(Component::class)->call(...)
 */
class AdminTradesCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin(): User
    {
        return User::factory()->admin()->create([
            'permissions'  => ['manage-services', 'perform-critical-admin-actions'],
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'is_active'    => true,
        ]);
    }

    public function test_admin_can_list_seeded_trades(): void
    {
        $this->seed(TradeSeeder::class);
        $admin = $this->createAdmin();

        $this->actingAs($admin);

        Livewire::test(AdminTradesComponent::class)
            ->assertSee('Nettoyage')
            ->assertSee('Bâtiment')
            ->assertSee('Peinture')
            ->assertSee('Levage');
    }

    public function test_admin_can_create_a_new_trade(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Livewire::test(AdminTradesComponent::class)
            ->call('openCreate')
            ->set('name', 'Plomberie')
            ->set('slug', 'plomberie')
            ->set('code', 'PLUMBING')
            ->set('color', '#3B82F6')
            ->set('is_active', true)
            ->set('sort_order', 60)
            ->call('save');

        $this->assertDatabaseHas('trades', [
            'slug' => 'plomberie',
            'code' => 'PLUMBING',
            'name' => 'Plomberie',
        ]);
    }

    public function test_creating_a_trade_with_duplicate_slug_fails(): void
    {
        Trade::create([
            'slug' => 'jardinage', 'code' => 'GARDEN', 'name' => 'Jardinage existant',
            'is_active' => true, 'sort_order' => 10,
        ]);

        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Livewire::test(AdminTradesComponent::class)
            ->call('openCreate')
            ->set('name', 'Jardinage 2')
            ->set('slug', 'jardinage')   // duplicate
            ->set('code', 'GARDEN_2')
            ->call('save')
            ->assertHasErrors(['slug']);

        $this->assertSame(1, Trade::where('slug', 'jardinage')->count());
    }

    public function test_admin_can_toggle_active_state(): void
    {
        $trade = Trade::create([
            'slug' => 'electricite', 'code' => 'ELEC', 'name' => 'Électricité',
            'is_active' => true, 'sort_order' => 100,
        ]);

        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Livewire::test(AdminTradesComponent::class)
            ->call('toggleActive', $trade->id);

        $trade->refresh();
        $this->assertFalse($trade->is_active);

        Livewire::test(AdminTradesComponent::class)
            ->call('toggleActive', $trade->id);

        $trade->refresh();
        $this->assertTrue($trade->is_active);
    }

    public function test_admin_can_reorder_trades(): void
    {
        $a = Trade::create(['slug' => 'a', 'code' => 'A', 'name' => 'A', 'sort_order' => 10, 'is_active' => true]);
        $b = Trade::create(['slug' => 'b', 'code' => 'B', 'name' => 'B', 'sort_order' => 20, 'is_active' => true]);

        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Livewire::test(AdminTradesComponent::class)
            ->call('moveUp', $b->id);

        $a->refresh();
        $b->refresh();

        $this->assertSame(20, $a->sort_order);
        $this->assertSame(10, $b->sort_order);
    }

    public function test_cannot_delete_trade_with_attached_services(): void
    {
        $trade = Trade::create([
            'slug' => 'demolition', 'code' => 'DEMO', 'name' => 'Démolition',
            'is_active' => true, 'sort_order' => 70,
        ]);
        // attach a service to the trade
        ServiceCatalog::create([
            'trade_id'  => $trade->id,
            'name'      => 'Démolition cloison',
            'slug'      => 'demolition-cloison',
            'code'      => 'DEMO_CLOISON',
            'is_active' => true,
            'base_price'=> 250,
            'currency'  => 'EUR',
            'default_duration_minutes' => 240,
        ]);

        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Livewire::test(AdminTradesComponent::class)
            ->call('delete', $trade->id);

        $this->assertNotNull(Trade::find($trade->id), 'Trade should not be soft-deleted while it still has services.');
    }
}

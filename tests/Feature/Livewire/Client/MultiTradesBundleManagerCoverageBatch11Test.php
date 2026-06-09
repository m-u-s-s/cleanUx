<?php

namespace Tests\Feature\Livewire\Client;

use App\Livewire\Client\MultiTradesBundleManager;
use App\Models\Booking;
use App\Models\MultiTradeBundle;
use App\Models\MultiTradeBundleItem;
use App\Models\Trade;
use App\Models\User;
use Database\Factories\MultiTradeBundleFactory;
use Database\Factories\MultiTradeBundleItemFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MultiTradesBundleManagerCoverageBatch11Test extends TestCase
{
    use RefreshDatabase;

    public function test_mount_seeds_two_empty_items(): void
    {
        $user = User::factory()->client()->create();

        Livewire::actingAs($user)
            ->test(MultiTradesBundleManager::class)
            ->assertOk()
            ->assertCount('items', 2)
            ->assertSet('tab', 'list');
    }

    public function test_add_and_remove_item_mutates_collection(): void
    {
        $user = User::factory()->client()->create();

        Livewire::actingAs($user)
            ->test(MultiTradesBundleManager::class)
            ->call('addItem')
            ->assertCount('items', 3)
            ->call('removeItem', 1)
            ->assertCount('items', 2)
            ->call('removeItem', 0)
            ->assertCount('items', 1);
    }

    public function test_set_tab_changes_active_tab(): void
    {
        $user = User::factory()->client()->create();

        Livewire::actingAs($user)
            ->test(MultiTradesBundleManager::class)
            ->call('setTab', 'create')
            ->assertSet('tab', 'create');
    }

    public function test_create_bundle_validates_required_fields(): void
    {
        $user = User::factory()->client()->create();

        Livewire::actingAs($user)
            ->test(MultiTradesBundleManager::class)
            ->set('bundleName', '')
            ->call('createBundle')
            ->assertHasErrors(['bundleName']);
    }

    public function test_create_bundle_persists_draft_and_resets_form(): void
    {
        $user = User::factory()->client()->create();
        $tradeA = Trade::factory()->create();
        $tradeB = Trade::factory()->create();

        Livewire::actingAs($user)
            ->test(MultiTradesBundleManager::class)
            ->set('bundleName', 'Rénovation cuisine')
            ->set('bundleDescription', 'Plomberie + peinture')
            ->set('items', [
                ['trade_id' => $tradeA->id, 'label' => 'Plomberie', 'description' => 'tuyaux', 'duration_minutes' => 120, 'estimated_price_eur' => 250.50],
                ['trade_id' => $tradeB->id, 'label' => 'Peinture', 'description' => '', 'duration_minutes' => 90, 'estimated_price_eur' => 180],
            ])
            ->call('createBundle')
            ->assertHasNoErrors()
            ->assertSet('tab', 'list')
            ->assertSet('bundleName', '')
            ->assertCount('items', 2)
            ->assertDispatched('toast');

        $bundle = MultiTradeBundle::query()->where('client_user_id', $user->id)->first();
        $this->assertNotNull($bundle);
        $this->assertSame('Rénovation cuisine', $bundle->name);
        $this->assertSame(MultiTradeBundle::STATUS_DRAFT, $bundle->status);
        // 250.50 -> 25050 cents, 180 -> 18000 cents
        $this->assertSame(43050, $bundle->total_estimated_cents);
        $this->assertCount(2, $bundle->items);
    }

    public function test_start_quoting_transitions_owned_bundle(): void
    {
        $user = User::factory()->client()->create();
        $bundle = MultiTradeBundleFactory::new()->create([
            'total_quoted_cents' => 0,
            'total_final_cents' => 0,
            'client_user_id' => $user->id,
            'status' => MultiTradeBundle::STATUS_DRAFT,
        ]);

        Livewire::actingAs($user)
            ->test(MultiTradesBundleManager::class)
            ->call('startQuoting', $bundle->id)
            ->assertDispatched('toast');

        $this->assertSame(MultiTradeBundle::STATUS_QUOTING, $bundle->fresh()->status);
    }

    public function test_start_quoting_ignores_bundle_not_owned(): void
    {
        $user = User::factory()->client()->create();
        $other = User::factory()->client()->create();
        $bundle = MultiTradeBundleFactory::new()->create([
            'total_quoted_cents' => 0,
            'total_final_cents' => 0,
            'client_user_id' => $other->id,
            'status' => MultiTradeBundle::STATUS_DRAFT,
        ]);

        Livewire::actingAs($user)
            ->test(MultiTradesBundleManager::class)
            ->call('startQuoting', $bundle->id)
            ->assertNotDispatched('toast');

        $this->assertSame(MultiTradeBundle::STATUS_DRAFT, $bundle->fresh()->status);
    }

    public function test_start_quoting_flashes_error_on_invalid_state(): void
    {
        $user = User::factory()->client()->create();
        $bundle = MultiTradeBundleFactory::new()->create([
            'total_quoted_cents' => 0,
            'total_final_cents' => 0,
            'client_user_id' => $user->id,
            'status' => MultiTradeBundle::STATUS_QUOTING,
        ]);

        Livewire::actingAs($user)
            ->test(MultiTradesBundleManager::class)
            ->call('startQuoting', $bundle->id)
            ->assertDispatched('toast');
    }

    public function test_accept_bundle_creates_missions(): void
    {
        $user = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();
        $bundle = MultiTradeBundleFactory::new()->create([
            'total_quoted_cents' => 0,
            'total_final_cents' => 0,
            'client_user_id' => $user->id,
            'status' => MultiTradeBundle::STATUS_QUOTED,
        ]);
        MultiTradeBundleItemFactory::new()->create([
            'quoted_price_cents' => 0,
            'bundle_id' => $bundle->id,
            'status' => MultiTradeBundleItem::STATUS_QUOTED,
            'assigned_provider_user_id' => $provider->id,
            'quoted_price_cents' => 30000,
            'duration_minutes' => 120,
        ]);

        Livewire::actingAs($user)
            ->test(MultiTradesBundleManager::class)
            ->call('acceptBundle', $bundle->id)
            ->assertDispatched('toast');

        $this->assertSame(MultiTradeBundle::STATUS_ACCEPTED, $bundle->fresh()->status);
        $this->assertTrue(Booking::query()->where('employe_id', $provider->id)->exists());
    }

    public function test_accept_bundle_flashes_error_when_items_not_quoted(): void
    {
        $user = User::factory()->client()->create();
        $bundle = MultiTradeBundleFactory::new()->create([
            'total_quoted_cents' => 0,
            'total_final_cents' => 0,
            'client_user_id' => $user->id,
            'status' => MultiTradeBundle::STATUS_QUOTING,
        ]);
        MultiTradeBundleItemFactory::new()->create([
            'quoted_price_cents' => 0,
            'bundle_id' => $bundle->id,
            'status' => MultiTradeBundleItem::STATUS_DRAFT,
        ]);

        Livewire::actingAs($user)
            ->test(MultiTradesBundleManager::class)
            ->call('acceptBundle', $bundle->id)
            ->assertDispatched('toast');

        $this->assertSame(MultiTradeBundle::STATUS_QUOTING, $bundle->fresh()->status);
    }

    public function test_accept_bundle_ignores_when_not_owner(): void
    {
        $user = User::factory()->client()->create();
        $other = User::factory()->client()->create();
        $bundle = MultiTradeBundleFactory::new()->create([
            'total_quoted_cents' => 0,
            'total_final_cents' => 0,
            'client_user_id' => $other->id,
            'status' => MultiTradeBundle::STATUS_QUOTED,
        ]);

        Livewire::actingAs($user)
            ->test(MultiTradesBundleManager::class)
            ->call('acceptBundle', $bundle->id)
            ->assertNotDispatched('toast');
    }

    public function test_cancel_bundle_marks_cancelled(): void
    {
        $user = User::factory()->client()->create();
        $bundle = MultiTradeBundleFactory::new()->create([
            'total_quoted_cents' => 0,
            'total_final_cents' => 0,
            'client_user_id' => $user->id,
            'status' => MultiTradeBundle::STATUS_DRAFT,
        ]);
        MultiTradeBundleItemFactory::new()->create([
            'quoted_price_cents' => 0,
            'bundle_id' => $bundle->id,
            'status' => MultiTradeBundleItem::STATUS_DRAFT,
        ]);

        Livewire::actingAs($user)
            ->test(MultiTradesBundleManager::class)
            ->call('cancelBundle', $bundle->id)
            ->assertDispatched('toast');

        $this->assertSame(MultiTradeBundle::STATUS_CANCELLED, $bundle->fresh()->status);
    }

    public function test_cancel_bundle_ignores_when_not_owner(): void
    {
        $user = User::factory()->client()->create();
        $other = User::factory()->client()->create();
        $bundle = MultiTradeBundleFactory::new()->create([
            'total_quoted_cents' => 0,
            'total_final_cents' => 0,
            'client_user_id' => $other->id,
            'status' => MultiTradeBundle::STATUS_DRAFT,
        ]);

        Livewire::actingAs($user)
            ->test(MultiTradesBundleManager::class)
            ->call('cancelBundle', $bundle->id)
            ->assertNotDispatched('toast');

        $this->assertSame(MultiTradeBundle::STATUS_DRAFT, $bundle->fresh()->status);
    }

    public function test_render_lists_bundles_and_active_trades(): void
    {
        $user = User::factory()->client()->create();
        $trade = Trade::factory()->create(['is_active' => true, 'name' => 'Plomberie Test']);
        Trade::factory()->create(['is_active' => false, 'name' => 'Inactive Trade']);
        $bundle = MultiTradeBundleFactory::new()->create([
            'total_quoted_cents' => 0,
            'total_final_cents' => 0,
            'client_user_id' => $user->id,
            'name' => 'Mon Chantier Visible',
        ]);
        MultiTradeBundleItemFactory::new()->create([
            'quoted_price_cents' => 0,
            'bundle_id' => $bundle->id,
            'trade_id' => $trade->id,
        ]);

        Livewire::actingAs($user)
            ->test(MultiTradesBundleManager::class)
            ->assertOk()
            ->assertSee('Mon Chantier Visible')
            ->assertViewHas('trades')
            ->assertViewHas('bundles');
    }
}

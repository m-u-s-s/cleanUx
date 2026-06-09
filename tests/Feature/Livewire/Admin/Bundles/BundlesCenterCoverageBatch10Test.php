<?php

namespace Tests\Feature\Livewire\Admin\Bundles;

use App\Livewire\Admin\Bundles\BundlesCenter;
use App\Models\MultiTradeBundle;
use App\Models\MultiTradeBundleItem;
use App\Models\Trade;
use App\Models\User;
use Database\Factories\MultiTradeBundleFactory;
use Database\Factories\MultiTradeBundleItemFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BundlesCenterCoverageBatch10Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->client = User::factory()->client()->create(['name' => 'Renov Client', 'email' => 'renov@example.com']);
    }

    private function makeBundle(array $overrides = []): MultiTradeBundle
    {
        return MultiTradeBundleFactory::new()->create(array_merge([
            'client_user_id' => $this->client->id,
            'status' => MultiTradeBundle::STATUS_DRAFT,
            'total_quoted_cents' => 0,
            'total_final_cents' => 0,
        ], $overrides));
    }

    private function makeItem(MultiTradeBundle $bundle, array $overrides = []): MultiTradeBundleItem
    {
        return MultiTradeBundleItemFactory::new()->create(array_merge([
            'bundle_id' => $bundle->id,
            'trade_id' => Trade::factory()->create()->id,
            'quoted_price_cents' => 0,
            'status' => MultiTradeBundleItem::STATUS_DRAFT,
        ], $overrides));
    }

    public function test_mount_renders_with_bundles_stats_and_selected(): void
    {
        $bundle = $this->makeBundle();
        $this->makeItem($bundle);

        Livewire::actingAs($this->admin)
            ->test(BundlesCenter::class)
            ->assertOk()
            ->assertViewHas('bundles')
            ->assertViewHas('stats')
            ->assertViewHas('selectedBundle')
            ->assertViewHas('availableProviders');
    }

    public function test_open_and_close_bundle(): void
    {
        $bundle = $this->makeBundle();
        $this->makeItem($bundle);

        Livewire::actingAs($this->admin)
            ->test(BundlesCenter::class)
            ->call('openBundle', $bundle->id)
            ->assertSet('selectedBundleId', $bundle->id)
            ->assertOk()
            ->call('closeBundle')
            ->assertSet('selectedBundleId', null)
            ->assertSet('assigningItemId', null);
    }

    public function test_open_assign_initializes_state(): void
    {
        $bundle = $this->makeBundle();
        $item = $this->makeItem($bundle);

        Livewire::actingAs($this->admin)
            ->test(BundlesCenter::class)
            ->call('openAssign', $item->id)
            ->assertSet('assigningItemId', $item->id)
            ->assertSet('assignProviderId', null)
            ->assertSet('assignQuotedEur', 0)
            ->assertOk();
    }

    public function test_assign_provider_quotes_item(): void
    {
        $provider = User::factory()->employe()->create(['is_active' => true]);
        $bundle = $this->makeBundle();
        $item = $this->makeItem($bundle);

        Livewire::actingAs($this->admin)
            ->test(BundlesCenter::class)
            ->set('assigningItemId', $item->id)
            ->set('assignProviderId', $provider->id)
            ->set('assignQuotedEur', 150)
            ->call('assignProvider')
            ->assertSet('assigningItemId', null)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('multi_trade_bundle_items', [
            'id' => $item->id,
            'assigned_provider_user_id' => $provider->id,
            'quoted_price_cents' => 15000,
            'status' => MultiTradeBundleItem::STATUS_QUOTED,
        ]);
    }

    public function test_assign_provider_returns_early_when_item_or_provider_missing(): void
    {
        Livewire::actingAs($this->admin)
            ->test(BundlesCenter::class)
            ->set('assigningItemId', 999999)
            ->set('assignProviderId', 888888)
            ->call('assignProvider')
            ->assertSet('assigningItemId', 999999);
    }

    public function test_assign_provider_dispatches_error_toast_on_invalid_state(): void
    {
        $provider = User::factory()->employe()->create();
        $bundle = $this->makeBundle();
        $item = $this->makeItem($bundle, ['status' => MultiTradeBundleItem::STATUS_ACCEPTED]);

        Livewire::actingAs($this->admin)
            ->test(BundlesCenter::class)
            ->set('assigningItemId', $item->id)
            ->set('assignProviderId', $provider->id)
            ->set('assignQuotedEur', 100)
            ->call('assignProvider')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('multi_trade_bundle_items', [
            'id' => $item->id,
            'status' => MultiTradeBundleItem::STATUS_ACCEPTED,
        ]);
    }

    public function test_force_cancel_cancels_bundle(): void
    {
        $bundle = $this->makeBundle();
        $this->makeItem($bundle);

        Livewire::actingAs($this->admin)
            ->test(BundlesCenter::class)
            ->call('openBundle', $bundle->id)
            ->call('forceCancel', $bundle->id)
            ->assertDispatched('toast')
            ->assertSet('selectedBundleId', null);

        $this->assertDatabaseHas('multi_trade_bundles', [
            'id' => $bundle->id,
            'status' => MultiTradeBundle::STATUS_CANCELLED,
        ]);
    }

    public function test_force_cancel_returns_early_when_bundle_missing(): void
    {
        Livewire::actingAs($this->admin)
            ->test(BundlesCenter::class)
            ->call('forceCancel', 777777)
            ->assertOk();
    }

    public function test_render_filters_by_status(): void
    {
        $this->makeBundle(['status' => MultiTradeBundle::STATUS_QUOTING, 'name' => 'Quoting Bundle']);
        $this->makeBundle(['status' => MultiTradeBundle::STATUS_DRAFT, 'name' => 'Draft Bundle']);

        Livewire::actingAs($this->admin)
            ->test(BundlesCenter::class)
            ->set('statusFilter', MultiTradeBundle::STATUS_QUOTING)
            ->assertOk()
            ->assertSee('Quoting Bundle')
            ->assertDontSee('Draft Bundle');
    }

    public function test_render_filters_by_search_on_name_and_client(): void
    {
        $this->makeBundle(['name' => 'FindableRenovation']);
        $this->makeBundle(['name' => 'OtherProject']);

        Livewire::actingAs($this->admin)
            ->test(BundlesCenter::class)
            ->set('search', 'FindableRenovation')
            ->assertOk()
            ->assertSee('FindableRenovation')
            ->assertDontSee('OtherProject');
    }

    public function test_render_loads_available_providers_when_assigning(): void
    {
        $provider = User::factory()->employe()->create(['is_active' => true, 'name' => 'Active Provider']);
        $bundle = $this->makeBundle();
        $item = $this->makeItem($bundle);

        Livewire::actingAs($this->admin)
            ->test(BundlesCenter::class)
            ->call('openAssign', $item->id)
            ->assertOk()
            ->assertViewHas('availableProviders', fn ($providers) => $providers->contains('id', $provider->id));
    }
}

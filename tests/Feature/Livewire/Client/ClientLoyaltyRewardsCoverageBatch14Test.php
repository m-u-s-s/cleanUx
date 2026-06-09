<?php

namespace Tests\Feature\Livewire\Client;

use App\Livewire\Client\ClientLoyaltyRewards;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTier;
use App\Models\User;
use Database\Factories\LoyaltyRewardFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientLoyaltyRewardsCoverageBatch14Test extends TestCase
{
    use RefreshDatabase;

    protected User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->client()->create();
    }

    public function test_render_defaults_with_no_loyalty_account(): void
    {
        LoyaltyRewardFactory::new()->create([
            'reward_type' => 'service_credit',
            'points_cost' => 100,
            'stock_remaining' => 5,
        ]);

        Livewire::actingAs($this->client)
            ->test(ClientLoyaltyRewards::class)
            ->assertOk()
            ->assertSet('tab', 'catalogue')
            ->assertViewHas('balance', 0)
            ->assertViewHas('tierName', 'Bronze');
    }

    public function test_render_reflects_account_balance_and_tier_name(): void
    {
        $tier = LoyaltyTier::factory()->create(['name' => 'Gold Elite']);
        LoyaltyAccount::factory()->create([
            'user_id' => $this->client->id,
            'current_tier_id' => $tier->id,
            'redeemable_points' => 750,
        ]);

        Livewire::actingAs($this->client)
            ->test(ClientLoyaltyRewards::class)
            ->assertOk()
            ->assertViewHas('balance', 750)
            ->assertViewHas('tierName', 'Gold Elite');
    }

    public function test_set_tab_updates_tab(): void
    {
        Livewire::actingAs($this->client)
            ->test(ClientLoyaltyRewards::class)
            ->call('setTab', 'redemptions')
            ->assertSet('tab', 'redemptions');
    }

    public function test_open_and_close_reward(): void
    {
        $reward = LoyaltyRewardFactory::new()->create([
            'points_cost' => 200,
            'stock_remaining' => 10,
        ]);

        Livewire::actingAs($this->client)
            ->test(ClientLoyaltyRewards::class)
            ->call('openReward', $reward->id)
            ->assertSet('selectedRewardId', $reward->id)
            ->call('closeReward')
            ->assertSet('selectedRewardId', null);
    }

    public function test_type_filter_narrows_catalogue(): void
    {
        LoyaltyRewardFactory::new()->create([
            'reward_type' => 'service_credit',
            'points_cost' => 100,
            'stock_remaining' => 5,
        ]);
        LoyaltyRewardFactory::new()->create([
            'reward_type' => 'physical_item',
            'points_cost' => 300,
            'stock_remaining' => 5,
        ]);

        Livewire::actingAs($this->client)
            ->test(ClientLoyaltyRewards::class)
            ->set('typeFilter', 'service_credit')
            ->assertOk();
    }

    public function test_redeem_success_dispatches_toast_and_clears_selection(): void
    {
        LoyaltyAccount::factory()->create([
            'user_id' => $this->client->id,
            'current_tier_id' => null,
            'redeemable_points' => 1000,
        ]);
        $reward = LoyaltyRewardFactory::new()->create([
            'reward_type' => 'service_credit',
            'points_cost' => 200,
            'min_tier_level' => 0,
            'stock_remaining' => 5,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->client)
            ->test(ClientLoyaltyRewards::class)
            ->set('selectedRewardId', $reward->id)
            ->call('redeem', $reward->id)
            ->assertSet('selectedRewardId', null)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('loyalty_redemptions', [
            'user_id' => $this->client->id,
            'reward_id' => $reward->id,
            'points_spent' => 200,
        ]);
    }

    public function test_redeem_validation_failure_dispatches_error_toast(): void
    {
        LoyaltyAccount::factory()->create([
            'user_id' => $this->client->id,
            'current_tier_id' => null,
            'redeemable_points' => 10,
        ]);
        $reward = LoyaltyRewardFactory::new()->create([
            'reward_type' => 'service_credit',
            'points_cost' => 500,
            'min_tier_level' => 0,
            'stock_remaining' => 5,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->client)
            ->test(ClientLoyaltyRewards::class)
            ->call('redeem', $reward->id)
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('loyalty_redemptions', [
            'user_id' => $this->client->id,
            'reward_id' => $reward->id,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Livewire\Admin\Loyalty\LoyaltyRewardsCenter;
use App\Models\LoyaltyRedemption;
use App\Models\LoyaltyReward;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tests Livewire admin pour le centre marketplace fidélité (récompenses + rédemptions).
 *
 * Calque sur AdminPromoCodesCenterTest:
 *   User::factory()->admin()->create([...])
 *   $this->actingAs($admin)
 *   Livewire::test(LoyaltyRewardsCenter::class)->call(...)
 */
class AdminLoyaltyRewardsCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin(): User
    {
        return User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'is_active' => true,
        ]);
    }

    /** LoyaltyReward n'utilise pas HasFactory → création directe. */
    protected function makeReward(array $overrides = []): LoyaltyReward
    {
        return LoyaltyReward::query()->create(array_merge([
            'code' => 'rwd_'.uniqid(),
            'name' => 'Discount 10€',
            'reward_type' => LoyaltyReward::TYPE_DISCOUNT_CODE,
            'category' => 'discount',
            'points_cost' => 100,
            'value_cents' => 1000,
            'currency' => 'EUR',
            'min_tier_level' => 0,
            'is_active' => true,
        ], $overrides));
    }

    protected function makeRedemption(LoyaltyReward $reward, array $overrides = []): LoyaltyRedemption
    {
        $user = User::factory()->client()->create();

        return LoyaltyRedemption::query()->create(array_merge([
            'code' => LoyaltyRedemption::generateCode(),
            'user_id' => $user->id,
            'reward_id' => $reward->id,
            'points_spent' => 100,
            'status' => LoyaltyRedemption::STATUS_PENDING,
            'delivery_method' => 'email_code',
        ], $overrides));
    }

    public function test_render_lists_existing_rewards(): void
    {
        $this->actingAs($this->createAdmin());

        $this->makeReward(['code' => 'RWD-ALPHA', 'name' => 'Alpha Reward']);
        $this->makeReward(['code' => 'RWD-BETA', 'name' => 'Beta Reward']);

        Livewire::test(LoyaltyRewardsCenter::class)
            ->assertOk()
            ->assertSet('tab', 'rewards')
            ->assertSee('RWD-ALPHA')
            ->assertSee('Beta Reward');
    }

    public function test_set_tab_switches_to_redemptions(): void
    {
        $this->actingAs($this->createAdmin());

        Livewire::test(LoyaltyRewardsCenter::class)
            ->call('setTab', 'redemptions')
            ->assertSet('tab', 'redemptions');
    }

    public function test_open_create_sets_defaults_and_shows_form(): void
    {
        $this->actingAs($this->createAdmin());

        Livewire::test(LoyaltyRewardsCenter::class)
            ->call('openCreate')
            ->assertSet('showForm', true)
            ->assertSet('editRewardId', null)
            ->assertSet('form_reward_type', LoyaltyReward::TYPE_DISCOUNT_CODE)
            ->assertSet('form_points_cost', 100)
            ->assertSet('form_is_active', true);
    }

    public function test_close_form_hides_form(): void
    {
        $this->actingAs($this->createAdmin());

        Livewire::test(LoyaltyRewardsCenter::class)
            ->call('openCreate')
            ->assertSet('showForm', true)
            ->call('closeForm')
            ->assertSet('showForm', false)
            ->assertSet('editRewardId', null);
    }

    public function test_save_creates_a_reward(): void
    {
        $this->actingAs($this->createAdmin());

        Livewire::test(LoyaltyRewardsCenter::class)
            ->set('form_code', 'rwd_new001')
            ->set('form_name', 'Free Cleaning')
            ->set('form_reward_type', LoyaltyReward::TYPE_SERVICE_CREDIT)
            ->set('form_category', 'service')
            ->set('form_points_cost', 250)
            ->set('form_value_cents', 2500)
            ->set('form_currency', 'EUR')
            ->set('form_min_tier_level', 1)
            ->set('form_stock_initial', 10)
            ->set('form_is_active', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('loyalty_rewards', [
            'code' => 'rwd_new001',
            'name' => 'Free Cleaning',
            'reward_type' => LoyaltyReward::TYPE_SERVICE_CREDIT,
            'points_cost' => 250,
            'value_cents' => 2500,
            'min_tier_level' => 1,
            'stock_initial' => 10,
            'stock_remaining' => 10,
        ]);
    }

    public function test_save_requires_a_name(): void
    {
        $this->actingAs($this->createAdmin());

        Livewire::test(LoyaltyRewardsCenter::class)
            ->set('form_code', 'rwd_noname')
            ->set('form_name', '')
            ->set('form_points_cost', 100)
            ->call('save')
            ->assertHasErrors('form_name');

        $this->assertDatabaseMissing('loyalty_rewards', ['code' => 'rwd_noname']);
    }

    public function test_save_rejects_invalid_points_cost(): void
    {
        $this->actingAs($this->createAdmin());

        Livewire::test(LoyaltyRewardsCenter::class)
            ->set('form_code', 'rwd_badpts')
            ->set('form_name', 'Bad Points')
            ->set('form_points_cost', 0)
            ->call('save')
            ->assertHasErrors('form_points_cost');

        $this->assertDatabaseMissing('loyalty_rewards', ['code' => 'rwd_badpts']);
    }

    public function test_open_edit_loads_form_then_save_updates_and_preserves_stock(): void
    {
        $this->actingAs($this->createAdmin());

        $reward = $this->makeReward([
            'code' => 'RWD-EDIT',
            'name' => 'Avant',
            'reward_type' => LoyaltyReward::TYPE_DISCOUNT_CODE,
            'points_cost' => 100,
            'stock_initial' => 20,
            'stock_remaining' => 5,
            'is_active' => true,
        ]);

        Livewire::test(LoyaltyRewardsCenter::class)
            ->call('openEdit', $reward->id)
            ->assertSet('editRewardId', $reward->id)
            ->assertSet('form_code', 'RWD-EDIT')
            ->assertSet('form_name', 'Avant')
            ->assertSet('showForm', true)
            ->set('form_name', 'Après')
            ->set('form_points_cost', 300)
            ->set('form_stock_initial', 99)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $reward->refresh();
        $this->assertSame('Après', $reward->name);
        $this->assertSame(300, $reward->points_cost);
        // stock_remaining doit être préservé (non écrasé) à l'édition
        $this->assertSame(5, $reward->stock_remaining);
        $this->assertSame(99, $reward->stock_initial);
    }

    public function test_toggle_active_flips_state(): void
    {
        $this->actingAs($this->createAdmin());

        $reward = $this->makeReward(['is_active' => true]);

        Livewire::test(LoyaltyRewardsCenter::class)
            ->call('toggleActive', $reward->id)
            ->assertDispatched('toast');

        $this->assertFalse((bool) $reward->fresh()->is_active);
    }

    public function test_search_filter_narrows_rewards(): void
    {
        $this->actingAs($this->createAdmin());

        $this->makeReward(['code' => 'RWD-FIND', 'name' => 'Findable']);
        $this->makeReward(['code' => 'RWD-HIDE', 'name' => 'Hidden']);

        Livewire::test(LoyaltyRewardsCenter::class)
            ->set('search', 'Findable')
            ->assertSee('RWD-FIND')
            ->assertDontSee('RWD-HIDE');
    }

    public function test_reward_type_filter_narrows_rewards(): void
    {
        $this->actingAs($this->createAdmin());

        $this->makeReward([
            'code' => 'RWD-DISC',
            'name' => 'Discount One',
            'reward_type' => LoyaltyReward::TYPE_DISCOUNT_CODE,
        ]);
        $this->makeReward([
            'code' => 'RWD-PHYS',
            'name' => 'Physical One',
            'reward_type' => LoyaltyReward::TYPE_PHYSICAL_ITEM,
        ]);

        Livewire::test(LoyaltyRewardsCenter::class)
            ->set('rewardTypeFilter', LoyaltyReward::TYPE_PHYSICAL_ITEM)
            ->assertSee('RWD-PHYS')
            ->assertDontSee('RWD-DISC');
    }

    public function test_status_filter_narrows_redemptions(): void
    {
        $this->actingAs($this->createAdmin());

        $reward = $this->makeReward();
        $this->makeRedemption($reward, [
            'code' => 'RDM-PEND',
            'status' => LoyaltyRedemption::STATUS_PENDING,
        ]);
        $this->makeRedemption($reward, [
            'code' => 'RDM-DELI',
            'status' => LoyaltyRedemption::STATUS_DELIVERED,
        ]);

        Livewire::test(LoyaltyRewardsCenter::class)
            ->set('tab', 'redemptions')
            ->set('statusFilter', LoyaltyRedemption::STATUS_DELIVERED)
            ->assertSee('RDM-DELI')
            ->assertDontSee('RDM-PEND');
    }

    public function test_mark_delivered_transitions_redemption(): void
    {
        $this->actingAs($this->createAdmin());

        $reward = $this->makeReward();
        $redemption = $this->makeRedemption($reward, [
            'status' => LoyaltyRedemption::STATUS_PENDING,
        ]);

        Livewire::test(LoyaltyRewardsCenter::class)
            ->call('markDelivered', $redemption->id)
            ->assertDispatched('toast');

        $this->assertSame(LoyaltyRedemption::STATUS_DELIVERED, $redemption->fresh()->status);
        $this->assertNotNull($redemption->fresh()->delivered_at);
    }

    public function test_cancel_redemption_cancels_when_cancellable(): void
    {
        $this->actingAs($this->createAdmin());

        $reward = $this->makeReward(['stock_remaining' => 3, 'stock_initial' => 5]);
        $redemption = $this->makeRedemption($reward, [
            'status' => LoyaltyRedemption::STATUS_PENDING,
            'points_spent' => 100,
        ]);

        Livewire::test(LoyaltyRewardsCenter::class)
            ->call('cancelRedemption', $redemption->id, 'Annulation administrative testée')
            ->assertDispatched('toast');

        $this->assertSame(LoyaltyRedemption::STATUS_CANCELLED, $redemption->fresh()->status);
    }

    public function test_cancel_redemption_error_branch_leaves_status_unchanged(): void
    {
        $this->actingAs($this->createAdmin());

        $reward = $this->makeReward();
        // Une rédemption livrée n'est pas annulable → le service lève, l'action attrape et toast erreur.
        $redemption = $this->makeRedemption($reward, [
            'status' => LoyaltyRedemption::STATUS_DELIVERED,
            'points_spent' => 100,
        ]);

        Livewire::test(LoyaltyRewardsCenter::class)
            ->call('cancelRedemption', $redemption->id, 'Tentative non annulable')
            ->assertDispatched('toast');

        $this->assertSame(LoyaltyRedemption::STATUS_DELIVERED, $redemption->fresh()->status);
    }
}

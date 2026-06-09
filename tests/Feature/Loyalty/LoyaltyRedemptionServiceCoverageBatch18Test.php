<?php

namespace Tests\Feature\Loyalty;

use App\Models\LoyaltyRedemption;
use App\Models\LoyaltyReward;
use App\Models\User;
use App\Services\Loyalty\LoyaltyRedemptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LoyaltyRedemptionServiceCoverageBatch18Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (Schema::hasTable('loyalty_tiers') && DB::table('loyalty_tiers')->count() === 0) {
            DB::table('loyalty_tiers')->insert([
                ['slug' => 'bronze', 'name' => 'Bronze', 'min_period_points' => 0, 'rank' => 0, 'discount_percent' => 0, 'priority_dispatch' => false, 'vip_support' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['slug' => 'silver', 'name' => 'Silver', 'min_period_points' => 500, 'rank' => 1, 'discount_percent' => 5, 'priority_dispatch' => false, 'vip_support' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['slug' => 'gold', 'name' => 'Gold', 'min_period_points' => 2000, 'rank' => 2, 'discount_percent' => 10, 'priority_dispatch' => true, 'vip_support' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['slug' => 'platinum', 'name' => 'Platinum', 'min_period_points' => 5000, 'rank' => 3, 'discount_percent' => 15, 'priority_dispatch' => true, 'vip_support' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    protected function seedBalance(User $user, int $points, string $tier = 'bronze'): void
    {
        $tierId = DB::table('loyalty_tiers')->where('slug', $tier)->value('id');
        DB::table('loyalty_accounts')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'redeemable_points' => $points,
                'lifetime_points' => $points,
                'period_points' => $points,
                'current_tier_id' => $tierId,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    protected function makeReward(array $overrides = []): LoyaltyReward
    {
        return LoyaltyReward::query()->create(array_merge([
            'code' => 'rwd_'.uniqid(),
            'name' => 'Discount 10€',
            'reward_type' => LoyaltyReward::TYPE_DISCOUNT_CODE,
            'points_cost' => 100,
            'value_cents' => 1000,
            'currency' => 'EUR',
            'is_active' => true,
        ], $overrides));
    }

    public function test_redeem_rejects_inactive_reward(): void
    {
        $user = User::factory()->create();
        $this->seedBalance($user, 500);
        $reward = $this->makeReward(['is_active' => false]);

        $this->expectException(ValidationException::class);
        app(LoyaltyRedemptionService::class)->redeem($user, $reward);
    }

    public function test_redeem_rejects_out_of_stock_reward(): void
    {
        $user = User::factory()->create();
        $this->seedBalance($user, 500);
        $reward = $this->makeReward(['stock_remaining' => 0, 'stock_initial' => 5]);

        $this->expectException(ValidationException::class);
        app(LoyaltyRedemptionService::class)->redeem($user, $reward);
    }

    public function test_redeem_rejects_insufficient_tier(): void
    {
        $user = User::factory()->create();
        $this->seedBalance($user, 500, 'bronze');
        $reward = $this->makeReward(['min_tier_level' => 3]);

        $this->expectException(ValidationException::class);
        app(LoyaltyRedemptionService::class)->redeem($user, $reward);
    }

    public function test_redeem_rejects_insufficient_points(): void
    {
        $user = User::factory()->create();
        $this->seedBalance($user, 50);
        $reward = $this->makeReward(['points_cost' => 1000]);

        $this->expectException(ValidationException::class);
        app(LoyaltyRedemptionService::class)->redeem($user, $reward);
    }

    public function test_redeem_decrements_stock_and_debits_points(): void
    {
        $user = User::factory()->create();
        $this->seedBalance($user, 500);
        $reward = $this->makeReward(['stock_remaining' => 3, 'stock_initial' => 3, 'points_cost' => 100]);

        $redemption = app(LoyaltyRedemptionService::class)->redeem($user, $reward);

        $this->assertSame(100, $redemption->points_spent);
        $this->assertSame(2, $reward->fresh()->stock_remaining);
        $this->assertSame(400, (int) DB::table('loyalty_accounts')->where('user_id', $user->id)->value('redeemable_points'));
    }

    public function test_cancel_credits_points_back_and_restocks(): void
    {
        $user = User::factory()->create();
        $this->seedBalance($user, 500);
        $reward = $this->makeReward(['stock_remaining' => 4, 'stock_initial' => 4, 'points_cost' => 100]);

        $service = app(LoyaltyRedemptionService::class);
        $redemption = $service->redeem($user, $reward);

        // After redeem: balance 400, stock 3
        $this->assertSame(3, $reward->fresh()->stock_remaining);

        $cancelled = $service->cancel($redemption, 'Demande de retour client confirmée');

        $this->assertSame(LoyaltyRedemption::STATUS_CANCELLED, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame('Demande de retour client confirmée', $cancelled->cancellation_reason);
        $this->assertSame(4, $reward->fresh()->stock_remaining);
        $this->assertSame(500, (int) DB::table('loyalty_accounts')->where('user_id', $user->id)->value('redeemable_points'));
    }

    public function test_resolve_tier_level_maps_platinum_to_highest(): void
    {
        $user = User::factory()->create();
        $this->seedBalance($user, 9000, 'platinum');
        $reward = $this->makeReward(['min_tier_level' => 3]);

        $redemption = app(LoyaltyRedemptionService::class)->redeem($user, $reward);

        $this->assertSame(LoyaltyRedemption::STATUS_CONFIRMED, $redemption->status);
    }
}

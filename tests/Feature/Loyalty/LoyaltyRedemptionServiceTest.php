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

class LoyaltyRedemptionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed les tiers si la table existe (le seeder n'est pas exécuté en test)
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
        if (! Schema::hasTable('loyalty_accounts')) {
            $this->markTestSkipped('loyalty_accounts table missing');
        }
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

    protected function createReward(array $overrides = []): LoyaltyReward
    {
        return LoyaltyReward::query()->create(array_merge([
            'code' => 'rwd_' . uniqid(),
            'name' => 'Discount 10€',
            'reward_type' => LoyaltyReward::TYPE_DISCOUNT_CODE,
            'points_cost' => 100,
            'value_cents' => 1000,
            'currency' => 'EUR',
            'is_active' => true,
        ], $overrides));
    }

    public function test_redeem_success_creates_row_and_debits_points(): void
    {
        $user = User::factory()->create();
        $this->seedBalance($user, 500);
        $reward = $this->createReward();

        $redemption = app(LoyaltyRedemptionService::class)->redeem($user, $reward);

        $this->assertInstanceOf(LoyaltyRedemption::class, $redemption);
        $this->assertSame(100, $redemption->points_spent);
        $this->assertNotEmpty($redemption->voucher_code);
        $this->assertSame(LoyaltyRedemption::STATUS_CONFIRMED, $redemption->status);
        $balance = (int) DB::table('loyalty_accounts')->where('user_id', $user->id)->value('redeemable_points');
        $this->assertSame(400, $balance);
    }

    public function test_redeem_rejects_insufficient_points(): void
    {
        $user = User::factory()->create();
        $this->seedBalance($user, 50);
        $reward = $this->createReward(['points_cost' => 100]);

        $this->expectException(ValidationException::class);
        app(LoyaltyRedemptionService::class)->redeem($user, $reward);
    }

    public function test_redeem_rejects_inactive_reward(): void
    {
        $user = User::factory()->create();
        $this->seedBalance($user, 500);
        $reward = $this->createReward(['is_active' => false]);

        $this->expectException(ValidationException::class);
        app(LoyaltyRedemptionService::class)->redeem($user, $reward);
    }

    public function test_redeem_rejects_out_of_stock(): void
    {
        $user = User::factory()->create();
        $this->seedBalance($user, 500);
        $reward = $this->createReward(['stock_remaining' => 0, 'stock_initial' => 5]);

        $this->expectException(ValidationException::class);
        app(LoyaltyRedemptionService::class)->redeem($user, $reward);
    }

    public function test_redeem_decrements_stock_when_finite(): void
    {
        $user = User::factory()->create();
        $this->seedBalance($user, 500);
        $reward = $this->createReward(['stock_remaining' => 3, 'stock_initial' => 5]);

        app(LoyaltyRedemptionService::class)->redeem($user, $reward);

        $this->assertSame(2, $reward->fresh()->stock_remaining);
    }

    public function test_redeem_rejects_when_tier_insufficient(): void
    {
        $user = User::factory()->create();
        $this->seedBalance($user, 500, 'bronze');   // tier 0
        $reward = $this->createReward(['min_tier_level' => 2]);   // gold required

        $this->expectException(ValidationException::class);
        app(LoyaltyRedemptionService::class)->redeem($user, $reward);
    }

    public function test_cancel_refunds_points_and_restocks(): void
    {
        $user = User::factory()->create();
        $this->seedBalance($user, 500);
        $reward = $this->createReward(['stock_remaining' => 3, 'stock_initial' => 5]);
        $redemption = app(LoyaltyRedemptionService::class)->redeem($user, $reward);

        app(LoyaltyRedemptionService::class)->cancel($redemption, 'Erreur de redemption testée');

        $balanceAfter = (int) DB::table('loyalty_accounts')->where('user_id', $user->id)->value('redeemable_points');
        $this->assertSame(500, $balanceAfter);   // crédités back
        $this->assertSame(3, $reward->fresh()->stock_remaining);   // restocké
        $this->assertSame(LoyaltyRedemption::STATUS_CANCELLED, $redemption->fresh()->status);
    }

    public function test_mark_delivered_transitions_status(): void
    {
        $user = User::factory()->create();
        $this->seedBalance($user, 500);
        $reward = $this->createReward(['reward_type' => LoyaltyReward::TYPE_PHYSICAL_ITEM]);
        $redemption = app(LoyaltyRedemptionService::class)->redeem($user, $reward);
        $this->assertSame(LoyaltyRedemption::STATUS_PENDING, $redemption->status);

        $delivered = app(LoyaltyRedemptionService::class)->markDelivered($redemption);

        $this->assertSame(LoyaltyRedemption::STATUS_DELIVERED, $delivered->status);
        $this->assertNotNull($delivered->delivered_at);
    }
}

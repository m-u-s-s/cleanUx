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

class LoyaltyRedemptionServiceCoverageBatch15Test extends TestCase
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

    public function test_service_credit_reward_is_pending_with_no_voucher(): void
    {
        $user = User::factory()->create();
        $this->seedBalance($user, 500);
        $reward = $this->makeReward(['reward_type' => LoyaltyReward::TYPE_SERVICE_CREDIT]);

        $redemption = app(LoyaltyRedemptionService::class)->redeem($user, $reward);

        $this->assertSame('in_app_credit', $redemption->delivery_method);
        $this->assertSame(LoyaltyRedemption::STATUS_PENDING, $redemption->status);
        $this->assertNull($redemption->voucher_code);
        $this->assertNull($redemption->confirmed_at);
    }

    public function test_charity_donation_reward_uses_manual_delivery(): void
    {
        $user = User::factory()->create();
        $this->seedBalance($user, 500);
        $reward = $this->makeReward(['reward_type' => LoyaltyReward::TYPE_CHARITY_DONATION]);

        $redemption = app(LoyaltyRedemptionService::class)->redeem($user, $reward);

        $this->assertSame('manual', $redemption->delivery_method);
        $this->assertSame(LoyaltyRedemption::STATUS_PENDING, $redemption->status);
        $this->assertNull($redemption->voucher_code);
    }

    public function test_partner_voucher_confirms_with_voucher_code(): void
    {
        $user = User::factory()->create();
        $this->seedBalance($user, 500);
        $reward = $this->makeReward([
            'reward_type' => LoyaltyReward::TYPE_PARTNER_VOUCHER,
            'valid_until' => now()->addDays(30),
        ]);

        $redemption = app(LoyaltyRedemptionService::class)->redeem($user, $reward);

        $this->assertSame('email_code', $redemption->delivery_method);
        $this->assertSame(LoyaltyRedemption::STATUS_CONFIRMED, $redemption->status);
        $this->assertStringStartsWith('CLX-', (string) $redemption->voucher_code);
        $this->assertNotNull($redemption->confirmed_at);
    }

    public function test_redeem_persists_shipping_address_and_metadata_opts(): void
    {
        $user = User::factory()->create();
        $this->seedBalance($user, 500);
        $reward = $this->makeReward(['reward_type' => LoyaltyReward::TYPE_PHYSICAL_ITEM]);

        $redemption = app(LoyaltyRedemptionService::class)->redeem($user, $reward, [
            'shipping_address' => '12 rue de Test, 1000 Bruxelles',
            'metadata' => ['note' => 'fragile'],
        ]);

        $this->assertSame('postal', $redemption->delivery_method);
        $this->assertSame('12 rue de Test, 1000 Bruxelles', $redemption->shipping_address);
        $this->assertSame(['note' => 'fragile'], $redemption->metadata);
    }

    public function test_redeem_succeeds_when_tier_is_sufficient(): void
    {
        $user = User::factory()->create();
        $this->seedBalance($user, 500, 'gold');
        $reward = $this->makeReward(['min_tier_level' => 2]);

        $redemption = app(LoyaltyRedemptionService::class)->redeem($user, $reward);

        $this->assertSame(LoyaltyRedemption::STATUS_CONFIRMED, $redemption->status);
    }

    public function test_cancel_rejects_short_reason(): void
    {
        $user = User::factory()->create();
        $this->seedBalance($user, 500);
        $reward = $this->makeReward();
        $redemption = app(LoyaltyRedemptionService::class)->redeem($user, $reward);

        $this->expectException(ValidationException::class);
        app(LoyaltyRedemptionService::class)->cancel($redemption, 'oops');
    }

    public function test_cancel_rejects_non_cancellable_state(): void
    {
        $user = User::factory()->create();
        $this->seedBalance($user, 500);
        $reward = $this->makeReward(['reward_type' => LoyaltyReward::TYPE_PHYSICAL_ITEM]);
        $redemption = app(LoyaltyRedemptionService::class)->redeem($user, $reward);
        app(LoyaltyRedemptionService::class)->markDelivered($redemption);

        $this->expectException(ValidationException::class);
        app(LoyaltyRedemptionService::class)->cancel($redemption->fresh(), 'Demande de retour client');
    }

    public function test_mark_delivered_is_idempotent_when_already_delivered(): void
    {
        $user = User::factory()->create();
        $this->seedBalance($user, 500);
        $reward = $this->makeReward(['reward_type' => LoyaltyReward::TYPE_PHYSICAL_ITEM]);
        $redemption = app(LoyaltyRedemptionService::class)->redeem($user, $reward);

        $first = app(LoyaltyRedemptionService::class)->markDelivered($redemption);
        $deliveredAt = $first->delivered_at;

        $second = app(LoyaltyRedemptionService::class)->markDelivered($first);

        $this->assertSame(LoyaltyRedemption::STATUS_DELIVERED, $second->status);
        $this->assertEquals($deliveredAt, $second->delivered_at);
    }
}

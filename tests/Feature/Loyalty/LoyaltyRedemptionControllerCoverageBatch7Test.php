<?php

namespace Tests\Feature\Loyalty;

use App\Models\LoyaltyRedemption;
use App\Models\LoyaltyReward;
use App\Models\User;
use App\Services\Loyalty\LoyaltyRedemptionService;
use Database\Seeders\LoyaltyTierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoyaltyRedemptionControllerCoverageBatch7Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LoyaltyTierSeeder::class);
    }

    private function seedBalance(User $user, int $points, string $tier = 'bronze'): void
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

    private function makeReward(array $overrides = []): LoyaltyReward
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

    public function test_catalogue_returns_active_in_stock_rewards(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $this->makeReward(['name' => 'Cheap', 'points_cost' => 50]);
        $this->makeReward(['name' => 'Pricey', 'points_cost' => 300]);
        // Inactive — must be excluded
        $this->makeReward(['name' => 'Inactive', 'is_active' => false]);
        // Out of stock — must be excluded
        $this->makeReward(['name' => 'Sold out', 'stock_remaining' => 0, 'stock_initial' => 5]);

        $response = $this->getJson('/api/client/loyalty/rewards');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Cheap', $names);
        $this->assertContains('Pricey', $names);
        $this->assertNotContains('Inactive', $names);
        $this->assertNotContains('Sold out', $names);
        // Sorted ascending by points_cost
        $this->assertSame('Cheap', $response->json('data.0.name'));
        $response->assertJsonStructure([
            'data' => [['id', 'code', 'name', 'reward_type', 'points_cost', 'value_cents', 'min_tier_level', 'stock_remaining']],
        ]);
    }

    public function test_catalogue_filters_by_type_and_limit(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $this->makeReward(['reward_type' => LoyaltyReward::TYPE_DISCOUNT_CODE]);
        $this->makeReward(['reward_type' => LoyaltyReward::TYPE_PHYSICAL_ITEM]);
        $this->makeReward(['reward_type' => LoyaltyReward::TYPE_PHYSICAL_ITEM]);

        $response = $this->getJson('/api/client/loyalty/rewards?type=physical_item&limit=1');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame(LoyaltyReward::TYPE_PHYSICAL_ITEM, $data[0]['reward_type']);
    }

    public function test_catalogue_rejects_invalid_type(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/client/loyalty/rewards?type=not_a_real_type')
            ->assertStatus(422);
    }

    public function test_redeem_success_returns_201_and_debits_points(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);
        $this->seedBalance($user, 500);
        $reward = $this->makeReward(['points_cost' => 100]);

        $response = $this->postJson('/api/client/loyalty/rewards/redeem', [
            'reward_id' => $reward->id,
        ]);

        $response->assertCreated();
        $this->assertSame(100, $response->json('data.points_spent'));
        $this->assertNotEmpty($response->json('data.code'));

        $this->assertDatabaseHas('loyalty_redemptions', [
            'id' => $response->json('data.id'),
            'user_id' => $user->id,
        ]);
        $balance = (int) DB::table('loyalty_accounts')->where('user_id', $user->id)->value('redeemable_points');
        $this->assertSame(400, $balance);
    }

    public function test_redeem_returns_422_when_insufficient_points(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);
        $this->seedBalance($user, 10);
        $reward = $this->makeReward(['points_cost' => 100]);

        $response = $this->postJson('/api/client/loyalty/rewards/redeem', [
            'reward_id' => $reward->id,
        ]);

        $response->assertStatus(422);
        $this->assertSame('validation_failed', $response->json('error'));
        $this->assertArrayHasKey('errors', $response->json());
    }

    public function test_redeem_validates_missing_reward_id(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/client/loyalty/rewards/redeem', [])
            ->assertStatus(422);
    }

    public function test_mine_lists_only_current_user_redemptions(): void
    {
        $user = User::factory()->client()->create();
        $other = User::factory()->client()->create();
        $this->seedBalance($user, 500);
        $this->seedBalance($other, 500);

        $reward = $this->makeReward(['points_cost' => 100]);
        $otherReward = $this->makeReward(['points_cost' => 100]);

        $service = app(LoyaltyRedemptionService::class);
        $service->redeem($user, $reward);
        $service->redeem($other, $otherReward);

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/client/loyalty/redemptions');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertNotNull($data[0]['reward']);
        $this->assertSame($reward->name, $data[0]['reward']['name']);
        $response->assertJsonStructure([
            'data' => [['id', 'code', 'reward' => ['id', 'name', 'reward_type'], 'status', 'points_spent', 'created_at']],
        ]);
    }

    public function test_mine_filters_by_status(): void
    {
        $user = User::factory()->client()->create();
        $this->seedBalance($user, 1000);

        $service = app(LoyaltyRedemptionService::class);
        // discount_code => confirmed; physical_item => pending
        $confirmed = $service->redeem($user, $this->makeReward(['reward_type' => LoyaltyReward::TYPE_DISCOUNT_CODE, 'points_cost' => 100]));
        $service->redeem($user, $this->makeReward(['reward_type' => LoyaltyReward::TYPE_PHYSICAL_ITEM, 'points_cost' => 100]));

        $this->assertSame(LoyaltyRedemption::STATUS_CONFIRMED, $confirmed->status);

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/client/loyalty/redemptions?status=confirmed&limit=10');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame(LoyaltyRedemption::STATUS_CONFIRMED, $data[0]['status']);
    }

    public function test_mine_rejects_invalid_status(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/client/loyalty/redemptions?status=bogus')
            ->assertStatus(422);
    }
}

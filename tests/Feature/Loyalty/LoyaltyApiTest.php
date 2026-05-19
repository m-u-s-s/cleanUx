<?php

namespace Tests\Feature\Loyalty;

use App\Models\LoyaltyTransaction;
use App\Models\User;
use App\Services\Loyalty\LoyaltyService;
use Database\Seeders\LoyaltyTierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoyaltyApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LoyaltyTierSeeder::class);
    }

    public function test_me_endpoint_returns_account_summary(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        // Donne quelques points pour que le compte existe
        app(LoyaltyService::class)->award($user, LoyaltyTransaction::TYPE_EARN_SIGNUP, 100, null, 'sgn');

        $response = $this->getJson('/api/client/loyalty/me');
        $response->assertOk();
        $response->assertJsonStructure([
            'lifetime_points',
            'period_points',
            'tier' => ['slug', 'name', 'icon', 'color', 'discount_percent'],
            'next_tier' => ['slug', 'name', 'points_to_reach'],
            'multiplier',
        ]);
        $this->assertSame('bronze', $response->json('tier.slug'));
    }

    public function test_transactions_endpoint_returns_user_only(): void
    {
        $userA = User::factory()->client()->create();
        $userB = User::factory()->client()->create();

        app(LoyaltyService::class)->award($userA, LoyaltyTransaction::TYPE_EARN_SIGNUP, 50, null, 'sgnA');
        app(LoyaltyService::class)->award($userB, LoyaltyTransaction::TYPE_EARN_SIGNUP, 50, null, 'sgnB');

        Sanctum::actingAs($userA);
        $response = $this->getJson('/api/client/loyalty/transactions');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('earn_signup_bonus', $data[0]['type']);
    }
}

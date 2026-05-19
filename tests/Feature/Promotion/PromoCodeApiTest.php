<?php

namespace Tests\Feature\Promotion;

use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PromoCodeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_validate_endpoint_returns_discount_for_valid_code(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        PromoCode::create([
            'code' => 'API15',
            'discount_type' => PromoCode::TYPE_PERCENT,
            'discount_value' => 15,
            'status' => PromoCode::STATUS_ACTIVE,
        ]);

        $response = $this->postJson('/api/client/promo-codes/validate', [
            'code' => 'api15',
            'amount' => 100,
        ]);

        $response->assertOk();
        $response->assertJson([
            'valid' => true,
            'code' => 'API15',
            'discount_type' => 'percent',
        ]);
        $this->assertEqualsWithDelta(15.0, $response->json('discount_amount'), 0.01);
    }

    public function test_validate_endpoint_returns_422_for_invalid_code(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/client/promo-codes/validate', [
            'code' => 'NOPE',
            'amount' => 100,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['valid' => false]);
    }

    public function test_referrals_me_returns_user_stats(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/client/referrals/me');

        $response->assertOk();
        $response->assertJsonStructure([
            'referral_code',
            'invite_url',
            'stats' => [
                'total_invited',
                'total_signed_up',
                'total_qualified',
                'total_rewarded',
                'total_earned',
            ],
            'rewards' => ['per_qualified_referrer', 'per_qualified_referee', 'currency'],
        ]);

        $this->assertNotEmpty($response->json('referral_code'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_plan_can_be_synced_to_premium_from_stripe_customer(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'stripe_id' => 'cus_test_123',
            'plan_type' => 'standard',
            'plan_status' => 'inactive',
        ]);

        $user->update([
            'plan_type' => 'premium',
            'plan_status' => 'active',
            'premium_started_at' => now(),
            'premium_renewal_at' => now()->addMonth(),
        ]);

        $this->assertEquals('premium', $user->fresh()->plan_type);
        $this->assertEquals('active', $user->fresh()->plan_status);
    }

    public function test_user_plan_can_be_synced_to_standard_when_subscription_cancelled(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'stripe_id' => 'cus_test_123',
            'plan_type' => 'premium',
            'plan_status' => 'active',
            'premium_started_at' => now(),
            'premium_renewal_at' => now()->addMonth(),
        ]);

        $user->update([
            'plan_type' => 'standard',
            'plan_status' => 'cancelled',
            'premium_started_at' => null,
            'premium_renewal_at' => null,
        ]);

        $this->assertEquals('standard', $user->fresh()->plan_type);
        $this->assertEquals('cancelled', $user->fresh()->plan_status);
    }
}
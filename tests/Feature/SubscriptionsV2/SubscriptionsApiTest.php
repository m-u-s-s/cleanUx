<?php

namespace Tests\Feature\SubscriptionsV2;

use App\Models\SubscriptionsV2\SubscriptionPlanV2;
use App\Models\SubscriptionsV2\SubscriptionV2;
use App\Models\User;
use App\Services\SubscriptionsV2\SubscriptionEngine;
use Database\Seeders\SubscriptionPlansV2Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlansV2Seeder::class);
        Config::set('subscriptions_v2.billing_driver', 'mock');
        Config::set('subscriptions_v2.allowed_currencies', ['EUR', 'USD']);
        Config::set('subscriptions_v2.default_currency', 'EUR');
        Config::set('subscriptions_v2.periods', [
            'weekly' => 7, 'biweekly' => 14, 'monthly' => 30, 'yearly' => 365,
        ]);
    }

    public function test_list_plans_returns_seeded_plans(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v2/subscriptions/plans');
        $response->assertOk();
        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertContains('cleaning_weekly_basic', $codes);
        $this->assertContains('maintenance_annual_basic', $codes);
    }

    public function test_subscribe_creates_subscription(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v2/subscriptions/me', [
            'plan_code' => 'cleaning_weekly_basic',
        ]);
        $response->assertCreated();
        $this->assertSame(1, SubscriptionV2::query()->where('user_id', $user->id)->count());
    }

    public function test_subscribe_returns_404_when_plan_not_found(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v2/subscriptions/me', [
            'plan_code' => 'unknown_plan',
        ])->assertStatus(404);
    }

    public function test_subscribe_requires_auth(): void
    {
        $this->postJson('/api/v2/subscriptions/me', [
            'plan_code' => 'cleaning_weekly_basic',
        ])->assertStatus(401);
    }

    public function test_list_my_subscriptions_returns_own_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $plan = SubscriptionPlanV2::query()->where('code', 'cleaning_weekly_basic')->first();
        app(SubscriptionEngine::class)->subscribe($user, $plan);
        app(SubscriptionEngine::class)->subscribe($other, $plan);

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/v2/subscriptions/me');
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_pause_then_resume_via_api(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlanV2::query()->where('code', 'cleaning_weekly_basic')->first();
        $sub = app(SubscriptionEngine::class)->subscribe($user, $plan);

        Sanctum::actingAs($user);
        $this->postJson("/api/v2/subscriptions/me/{$sub->id}/pause")->assertOk();
        $this->assertSame(SubscriptionV2::STATUS_PAUSED, $sub->fresh()->status);

        $this->postJson("/api/v2/subscriptions/me/{$sub->id}/resume")->assertOk();
        $this->assertSame(SubscriptionV2::STATUS_ACTIVE, $sub->fresh()->status);
    }

    public function test_cancel_via_api_with_immediate_flag(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlanV2::query()->where('code', 'cleaning_weekly_basic')->first();
        $sub = app(SubscriptionEngine::class)->subscribe($user, $plan);

        Sanctum::actingAs($user);
        $this->postJson("/api/v2/subscriptions/me/{$sub->id}/cancel", ['immediate' => true])->assertOk();
        $this->assertSame(SubscriptionV2::STATUS_CANCELLED, $sub->fresh()->status);
    }

    public function test_action_on_other_user_subscription_forbidden(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $plan = SubscriptionPlanV2::query()->where('code', 'cleaning_weekly_basic')->first();
        $sub = app(SubscriptionEngine::class)->subscribe($other, $plan);

        Sanctum::actingAs($user);
        $this->postJson("/api/v2/subscriptions/me/{$sub->id}/cancel")->assertStatus(403);
    }

    public function test_change_plan_updates_plan(): void
    {
        $user = User::factory()->create();
        $planA = SubscriptionPlanV2::query()->where('code', 'cleaning_weekly_basic')->first();
        $planB = SubscriptionPlanV2::query()->where('code', 'cleaning_biweekly_premium')->first();
        $sub = app(SubscriptionEngine::class)->subscribe($user, $planA);

        Sanctum::actingAs($user);
        $response = $this->postJson("/api/v2/subscriptions/me/{$sub->id}/change-plan", [
            'plan_code' => 'cleaning_biweekly_premium',
        ]);
        $response->assertOk();
        $this->assertSame($planB->id, $sub->fresh()->plan_id);
    }

    public function test_list_my_cycles_returns_cycles(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlanV2::query()->where('code', 'cleaning_weekly_basic')->first();
        $sub = app(SubscriptionEngine::class)->subscribe($user, $plan);

        Sanctum::actingAs($user);
        $response = $this->getJson("/api/v2/subscriptions/me/{$sub->id}/cycles");
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_admin_list_subscriptions_returns_all(): void
    {
        $admin = User::factory()->admin()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $plan = SubscriptionPlanV2::query()->where('code', 'cleaning_weekly_basic')->first();
        app(SubscriptionEngine::class)->subscribe($a, $plan);
        app(SubscriptionEngine::class)->subscribe($b, $plan);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/subscriptions-v2/subscriptions');
        $response->assertOk();
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }
}

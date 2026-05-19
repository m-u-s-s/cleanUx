<?php

namespace Tests\Feature\SubscriptionsV2;

use App\Models\SubscriptionsV2\SubscriptionCycleV2;
use App\Models\SubscriptionsV2\SubscriptionPlanV2;
use App\Models\SubscriptionsV2\SubscriptionV2;
use App\Models\User;
use App\Services\SubscriptionsV2\SubscriptionEngine;
use Database\Seeders\SubscriptionPlansV2Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SubscriptionEngineTest extends TestCase
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
            'weekly' => 7, 'biweekly' => 14, 'monthly' => 30,
            'quarterly' => 91, 'yearly' => 365,
        ]);
    }

    public function test_subscribe_creates_subscription_and_first_cycle(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlanV2::query()->where('code', 'cleaning_weekly_basic')->first();

        $sub = app(SubscriptionEngine::class)->subscribe($user, $plan);

        $this->assertInstanceOf(SubscriptionV2::class, $sub);
        $this->assertSame($user->id, $sub->user_id);
        $this->assertSame($plan->id, $sub->plan_id);
        $this->assertSame(SubscriptionV2::STATUS_ACTIVE, $sub->status);
        $this->assertNotNull($sub->current_cycle_end);
        $this->assertSame(1, SubscriptionCycleV2::query()->where('subscription_id', $sub->id)->count());
    }

    public function test_subscribe_with_trial_starts_trialing(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlanV2::query()->where('code', 'cleaning_biweekly_premium')->first();

        $sub = app(SubscriptionEngine::class)->subscribe($user, $plan);

        $this->assertSame(SubscriptionV2::STATUS_TRIALING, $sub->status);
        $this->assertNotNull($sub->trial_ends_at);
        $this->assertTrue($sub->trial_ends_at->isFuture());
    }

    public function test_subscribe_idempotent_returns_existing_active(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlanV2::query()->where('code', 'cleaning_weekly_basic')->first();

        $a = app(SubscriptionEngine::class)->subscribe($user, $plan);
        $b = app(SubscriptionEngine::class)->subscribe($user, $plan);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, SubscriptionV2::query()->where('user_id', $user->id)->count());
    }

    public function test_subscribe_rejects_inactive_plan(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlanV2::query()->where('code', 'cleaning_weekly_basic')->first();
        $plan->update(['is_active' => false]);

        $this->expectException(ValidationException::class);
        app(SubscriptionEngine::class)->subscribe($user, $plan);
    }

    public function test_subscribe_rejects_unsupported_currency(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlanV2::query()->where('code', 'cleaning_weekly_basic')->first();

        $this->expectException(ValidationException::class);
        app(SubscriptionEngine::class)->subscribe($user, $plan, ['currency' => 'XYZ']);
    }

    public function test_pause_marks_paused(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlanV2::query()->where('code', 'cleaning_weekly_basic')->first();
        $sub = app(SubscriptionEngine::class)->subscribe($user, $plan);

        $paused = app(SubscriptionEngine::class)->pause($sub);
        $this->assertSame(SubscriptionV2::STATUS_PAUSED, $paused->status);
        $this->assertNotNull($paused->paused_at);
    }

    public function test_resume_only_works_on_paused(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlanV2::query()->where('code', 'cleaning_weekly_basic')->first();
        $sub = app(SubscriptionEngine::class)->subscribe($user, $plan);

        $this->expectException(ValidationException::class);
        app(SubscriptionEngine::class)->resume($sub);
    }

    public function test_resume_after_pause(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlanV2::query()->where('code', 'cleaning_weekly_basic')->first();
        $sub = app(SubscriptionEngine::class)->subscribe($user, $plan);
        $sub = app(SubscriptionEngine::class)->pause($sub);

        $resumed = app(SubscriptionEngine::class)->resume($sub);
        $this->assertSame(SubscriptionV2::STATUS_ACTIVE, $resumed->status);
        $this->assertNull($resumed->paused_at);
    }

    public function test_cancel_immediate_sets_cancelled_now(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlanV2::query()->where('code', 'cleaning_weekly_basic')->first();
        $sub = app(SubscriptionEngine::class)->subscribe($user, $plan);

        $cancelled = app(SubscriptionEngine::class)->cancel($sub, immediate: true);
        $this->assertSame(SubscriptionV2::STATUS_CANCELLED, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertFalse((bool) $cancelled->cancel_at_period_end);
    }

    public function test_cancel_period_end_keeps_active_until_end(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlanV2::query()->where('code', 'cleaning_weekly_basic')->first();
        $sub = app(SubscriptionEngine::class)->subscribe($user, $plan);

        $cancelled = app(SubscriptionEngine::class)->cancel($sub, immediate: false);
        $this->assertNotSame(SubscriptionV2::STATUS_CANCELLED, $cancelled->status);
        $this->assertTrue((bool) $cancelled->cancel_at_period_end);
        $this->assertNotNull($cancelled->ends_at);
    }

    public function test_change_plan_updates_plan_and_tracks_history(): void
    {
        $user = User::factory()->create();
        $planA = SubscriptionPlanV2::query()->where('code', 'cleaning_weekly_basic')->first();
        $planB = SubscriptionPlanV2::query()->where('code', 'cleaning_biweekly_premium')->first();
        $sub = app(SubscriptionEngine::class)->subscribe($user, $planA);

        $changed = app(SubscriptionEngine::class)->changePlan($sub, $planB);
        $this->assertSame($planB->id, $changed->plan_id);
        $this->assertSame('cleaning_weekly_basic', data_get($changed->metadata, 'previous_plan_code'));
    }
}

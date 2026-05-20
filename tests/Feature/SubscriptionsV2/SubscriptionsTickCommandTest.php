<?php

namespace Tests\Feature\SubscriptionsV2;

use App\Models\SubscriptionsV2\SubscriptionCycleV2;
use App\Models\SubscriptionsV2\SubscriptionPlanV2;
use App\Models\User;
use App\Services\SubscriptionsV2\SubscriptionEngine;
use Database\Seeders\SubscriptionPlansV2Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SubscriptionsTickCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlansV2Seeder::class);
        Config::set('subscriptions_v2.billing_driver', 'mock');
        Config::set('subscriptions_v2.allowed_currencies', ['EUR']);
        Config::set('subscriptions_v2.default_currency', 'EUR');
        Config::set('subscriptions_v2.periods', [
            'weekly' => 7, 'biweekly' => 14, 'monthly' => 30, 'yearly' => 365,
        ]);
    }

    public function test_tick_command_processes_due_subscriptions(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlanV2::query()->where('code', 'cleaning_weekly_basic')->first();
        $sub = app(SubscriptionEngine::class)->subscribe($user, $plan);
        // Force next_billing_at into the past
        $sub->update(['next_billing_at' => now()->subDay()]);

        $this->artisan('subscriptions:tick --limit=10')
            ->expectsOutputToContain('Found 1 due subscription')
            ->expectsOutputToContain('Tick complete')
            ->assertSuccessful();

        // First cycle should now be paid via mock provider
        $paid = SubscriptionCycleV2::query()
            ->where('subscription_id', $sub->id)
            ->where('billing_status', SubscriptionCycleV2::STATUS_PAID)
            ->exists();
        $this->assertTrue($paid);
    }

    public function test_tick_dry_run_does_not_process(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlanV2::query()->where('code', 'cleaning_weekly_basic')->first();
        $sub = app(SubscriptionEngine::class)->subscribe($user, $plan);
        $sub->update(['next_billing_at' => now()->subDay()]);

        $this->artisan('subscriptions:tick --dry-run')
            ->expectsOutputToContain('(dry-run)')
            ->assertSuccessful();

        $this->assertSame(0, SubscriptionCycleV2::query()
            ->where('subscription_id', $sub->id)
            ->where('billing_status', SubscriptionCycleV2::STATUS_PAID)
            ->count());
    }

    public function test_tick_skips_when_module_disabled(): void
    {
        Config::set('subscriptions_v2.enabled', false);
        $this->artisan('subscriptions:tick')
            ->expectsOutputToContain('disabled')
            ->assertSuccessful();
    }
}

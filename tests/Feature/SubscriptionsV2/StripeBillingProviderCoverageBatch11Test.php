<?php

namespace Tests\Feature\SubscriptionsV2;

use App\Models\SubscriptionsV2\SubscriptionCycleV2;
use App\Models\SubscriptionsV2\SubscriptionPlanV2;
use App\Models\SubscriptionsV2\SubscriptionV2;
use App\Models\User;
use App\Services\SubscriptionsV2\Providers\StripeBillingProvider;
use App\Services\SubscriptionsV2\SubscriptionEngine;
use Database\Seeders\SubscriptionPlansV2Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Stripe\Stripe;
use Tests\TestCase;

class StripeBillingProviderCoverageBatch11Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlansV2Seeder::class);
        Config::set('subscriptions_v2.allowed_currencies', ['EUR', 'USD']);
        Config::set('subscriptions_v2.periods', [
            'weekly' => 7, 'biweekly' => 14, 'monthly' => 30, 'yearly' => 365,
        ]);
    }

    private function makeCycle(): SubscriptionCycleV2
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlanV2::query()->where('code', 'cleaning_weekly_basic')->first();
        $sub = app(SubscriptionEngine::class)->subscribe($user, $plan);

        return $sub->cycles()->first();
    }

    public function test_name_returns_stripe_identifier(): void
    {
        $this->assertSame('stripe', (new StripeBillingProvider)->name());
    }

    public function test_charge_returns_no_user_when_subscription_has_no_user(): void
    {
        // user_id is NOT NULL + cascadeOnDelete, so build an in-memory subscription
        // with a null foreign key — belongsTo short-circuits to a null user.
        $sub = new SubscriptionV2(['code' => 'sub_no_user', 'billing_currency' => 'EUR']);
        $cycle = new SubscriptionCycleV2(['cycle_number' => 1, 'planned_amount_cents' => 4200]);
        $cycle->setRelation('subscription', $sub);

        $result = (new StripeBillingProvider)->chargeCycle($cycle);

        $this->assertFalse($result->success);
        $this->assertSame('no_user', $result->error);
        $this->assertSame('stripe', $result->provider);
        $this->assertSame(4200, $result->amountCents);
        $this->assertSame('EUR', $result->currency);
    }

    public function test_charge_returns_no_stripe_customer_when_user_missing_stripe_id(): void
    {
        $cycle = $this->makeCycle();

        $result = (new StripeBillingProvider)->chargeCycle($cycle);

        $this->assertFalse($result->success);
        $this->assertSame('no_stripe_customer', $result->error);
        $this->assertSame('stripe', $result->provider);
    }

    public function test_charge_returns_not_configured_when_no_stripe_key(): void
    {
        $cycle = $this->makeCycle();
        $cycle->subscription->user->forceFill(['stripe_id' => 'cus_test_configured'])->save();

        Config::set('cashier.secret', '');
        Config::set('services.stripe.secret', '');

        $result = (new StripeBillingProvider)->chargeCycle($cycle->fresh());

        $this->assertFalse($result->success);
        $this->assertSame('stripe_not_configured', $result->error);
        $this->assertSame('stripe', $result->provider);
    }

    public function test_charge_soft_fails_with_exception_when_stripe_call_throws(): void
    {
        $cycle = $this->makeCycle();
        $cycle->subscription->user->forceFill(['stripe_id' => 'cus_test_exception'])->save();

        Config::set('cashier.secret', 'sk_test_fake_key');
        Config::set('services.stripe.secret', 'sk_test_fake_key');

        $previousBase = Stripe::$apiBase;
        // Point the SDK at a dead local port so the off-session charge fails fast offline.
        Stripe::$apiBase = 'http://127.0.0.1:9';

        try {
            $result = (new StripeBillingProvider)->chargeCycle($cycle->fresh());
        } finally {
            Stripe::$apiBase = $previousBase;
        }

        $this->assertFalse($result->success);
        $this->assertStringStartsWith('exception:', (string) $result->error);
        $this->assertSame('stripe', $result->provider);
        $this->assertSame($cycle->planned_amount_cents, $result->amountCents);
    }
}

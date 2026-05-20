<?php

namespace Tests\Feature\Integration;

use App\Models\SubscriptionsV2\SubscriptionCycleV2;
use App\Models\SubscriptionsV2\SubscriptionInvoiceV2;
use App\Models\SubscriptionsV2\SubscriptionPlanV2;
use App\Models\SubscriptionsV2\SubscriptionV2;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\SubscriptionsV2\SubscriptionEngine;
use Database\Seeders\SubscriptionPlansV2Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Test E2E Subscriptions v2 : subscribe → cycle → tick → bill → cancel.
 * Vérifie que les webhooks subscription.created + subscription.cancelled
 * sont émis et que tout le chain produit les rows attendues.
 */
class SubscriptionLifecycleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        $this->seed(SubscriptionPlansV2Seeder::class);

        Config::set('subscriptions_v2.billing_driver', 'mock');
        Config::set('subscriptions_v2.allowed_currencies', ['EUR']);
        Config::set('subscriptions_v2.default_currency', 'EUR');
        Config::set('subscriptions_v2.periods', [
            'weekly' => 7, 'biweekly' => 14, 'monthly' => 30, 'yearly' => 365,
        ]);
        Config::set('subscriptions_v2.max_consecutive_failed_charges', 3);

        Config::set('webhooks_v2.enabled', true);
        Config::set('webhooks_v2.allowed_events', [
            'subscription.created', 'subscription.cancelled',
        ]);
    }

    public function test_subscribe_creates_subscription_webhook_and_first_cycle(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlanV2::query()->where('code', 'cleaning_weekly_basic')->first();

        $sub = app(SubscriptionEngine::class)->subscribe($user, $plan);

        $this->assertInstanceOf(SubscriptionV2::class, $sub);
        $this->assertSame(SubscriptionV2::STATUS_ACTIVE, $sub->status);

        // Cycle créé automatiquement
        $this->assertSame(1, SubscriptionCycleV2::query()->where('subscription_id', $sub->id)->count());

        // Webhook event subscription.created émis
        $this->assertSame(1, WebhookEvent::query()
            ->where('event_code', 'subscription.created')
            ->where('source_id', $sub->id)
            ->count());
    }

    public function test_tick_bills_cycle_and_marks_paid(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlanV2::query()->where('code', 'cleaning_weekly_basic')->first();
        $sub = app(SubscriptionEngine::class)->subscribe($user, $plan);
        $sub->update(['next_billing_at' => now()->subDay()]);

        app(SubscriptionEngine::class)->tick($sub);

        $cycle = SubscriptionCycleV2::query()->where('subscription_id', $sub->id)->first();
        $this->assertSame(SubscriptionCycleV2::STATUS_PAID, $cycle->billing_status);

        $invoice = SubscriptionInvoiceV2::query()->where('subscription_id', $sub->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame(SubscriptionInvoiceV2::STATUS_PAID, $invoice->status);

        $sub->refresh();
        $this->assertSame(1, $sub->billing_cycle_count);
        $this->assertGreaterThan(0, (int) $sub->total_billed_cents);
    }

    public function test_cancel_emits_webhook_and_sets_cancelled_at(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlanV2::query()->where('code', 'cleaning_weekly_basic')->first();
        $sub = app(SubscriptionEngine::class)->subscribe($user, $plan);

        app(SubscriptionEngine::class)->cancel($sub, immediate: true);

        $sub->refresh();
        $this->assertSame(SubscriptionV2::STATUS_CANCELLED, $sub->status);
        $this->assertNotNull($sub->cancelled_at);

        $this->assertSame(1, WebhookEvent::query()
            ->where('event_code', 'subscription.cancelled')
            ->where('source_id', $sub->id)
            ->count());
    }

    public function test_billing_failure_auto_cancels_after_max_failures(): void
    {
        // Test direct sur BillingProcessor (sans passer par tick() qui reset next_billing_at).
        // 3 échecs consécutifs sur le même cycle → auto-cancel.
        $user = User::factory()->create();
        $plan = SubscriptionPlanV2::query()->where('code', 'cleaning_weekly_basic')->first();
        $sub = app(SubscriptionEngine::class)->subscribe($user, $plan, [
            'metadata' => ['force_fail_billing' => true],
        ]);
        $cycle = SubscriptionCycleV2::query()->where('subscription_id', $sub->id)->first();
        $processor = app(\App\Services\SubscriptionsV2\BillingProcessor::class);

        for ($i = 0; $i < 3; $i++) {
            $processor->processCycle($cycle->fresh());
        }

        $sub->refresh();
        $this->assertSame(SubscriptionV2::STATUS_CANCELLED, $sub->status);
        $this->assertNotNull($sub->cancelled_at);
    }
}

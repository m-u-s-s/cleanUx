<?php

namespace Tests\Feature\SubscriptionsV2;

use App\Models\SubscriptionsV2\SubscriptionCycleV2;
use App\Models\SubscriptionsV2\SubscriptionInvoiceV2;
use App\Models\SubscriptionsV2\SubscriptionPlanV2;
use App\Models\SubscriptionsV2\SubscriptionV2;
use App\Models\User;
use App\Services\SubscriptionsV2\BillingProcessor;
use App\Services\SubscriptionsV2\SubscriptionEngine;
use Database\Seeders\SubscriptionPlansV2Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class BillingProcessorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlansV2Seeder::class);
        Config::set('subscriptions_v2.billing_driver', 'mock');
        Config::set('subscriptions_v2.max_consecutive_failed_charges', 3);
        Config::set('subscriptions_v2.allowed_currencies', ['EUR', 'USD']);
        Config::set('subscriptions_v2.periods', [
            'weekly' => 7, 'biweekly' => 14, 'monthly' => 30, 'yearly' => 365,
        ]);
    }

    private function subscribe(bool $forceFail = false): SubscriptionV2
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlanV2::query()->where('code', 'cleaning_weekly_basic')->first();
        $sub = app(SubscriptionEngine::class)->subscribe($user, $plan, [
            'metadata' => $forceFail ? ['force_fail_billing' => true] : [],
        ]);
        return $sub;
    }

    public function test_process_cycle_success_marks_paid_and_increments_counters(): void
    {
        $sub = $this->subscribe();
        $cycle = $sub->cycles()->first();

        $result = app(BillingProcessor::class)->processCycle($cycle);

        $this->assertSame(SubscriptionCycleV2::STATUS_PAID, $result->billing_status);
        $this->assertNotNull($result->billed_at);
        $this->assertSame($cycle->planned_amount_cents, (int) $result->billed_amount_cents);

        $sub->refresh();
        $this->assertSame(1, $sub->billing_cycle_count);
        $this->assertSame($cycle->planned_amount_cents, (int) $sub->total_billed_cents);
        $this->assertSame(0, $sub->consecutive_failed_charges);

        $this->assertSame(1, SubscriptionInvoiceV2::query()->where('subscription_id', $sub->id)->where('status', 'paid')->count());
    }

    public function test_process_cycle_idempotent_when_already_paid(): void
    {
        $sub = $this->subscribe();
        $cycle = $sub->cycles()->first();
        app(BillingProcessor::class)->processCycle($cycle);

        $invoicesBefore = SubscriptionInvoiceV2::query()->count();
        app(BillingProcessor::class)->processCycle($cycle->fresh());
        $this->assertSame($invoicesBefore, SubscriptionInvoiceV2::query()->count());
    }

    public function test_process_cycle_failure_marks_past_due_and_increments_failed_count(): void
    {
        $sub = $this->subscribe(forceFail: true);
        $cycle = $sub->cycles()->first();

        $result = app(BillingProcessor::class)->processCycle($cycle);

        $this->assertSame(SubscriptionCycleV2::STATUS_FAILED, $result->billing_status);
        $this->assertStringContainsString('mock_forced_failure', (string) $result->last_error);

        $sub->refresh();
        $this->assertSame(SubscriptionV2::STATUS_PAST_DUE, $sub->status);
        $this->assertSame(1, $sub->consecutive_failed_charges);
    }

    public function test_auto_cancel_after_max_consecutive_failures(): void
    {
        $sub = $this->subscribe(forceFail: true);
        $cycle = $sub->cycles()->first();

        // 3 failures consécutifs = auto-cancel
        for ($i = 0; $i < 3; $i++) {
            app(BillingProcessor::class)->processCycle($cycle->fresh());
        }

        $sub->refresh();
        $this->assertSame(SubscriptionV2::STATUS_CANCELLED, $sub->status);
        $this->assertNotNull($sub->cancelled_at);
    }

    public function test_recovery_from_past_due_when_charge_succeeds(): void
    {
        $sub = $this->subscribe(forceFail: true);
        $cycle = $sub->cycles()->first();
        app(BillingProcessor::class)->processCycle($cycle);
        $this->assertSame(SubscriptionV2::STATUS_PAST_DUE, $sub->fresh()->status);

        // Remove force_fail flag and re-process
        $sub->update(['metadata' => []]);
        app(BillingProcessor::class)->processCycle($cycle->fresh());

        $this->assertSame(SubscriptionV2::STATUS_ACTIVE, $sub->fresh()->status);
        $this->assertSame(0, $sub->fresh()->consecutive_failed_charges);
    }
}

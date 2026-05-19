<?php

namespace App\Services\SubscriptionsV2\Providers;

use App\Models\SubscriptionsV2\SubscriptionCycleV2;
use App\Services\SubscriptionsV2\BillingResult;
use App\Services\SubscriptionsV2\Contracts\BillingProviderContract;
use Illuminate\Support\Str;

/**
 * Mock provider for CI/staging — toujours succès sauf si le subscription metadata
 * `force_fail_billing=true` (utile pour tester past_due flow).
 */
class MockBillingProvider implements BillingProviderContract
{
    public function name(): string
    {
        return 'mock';
    }

    public function chargeCycle(SubscriptionCycleV2 $cycle): BillingResult
    {
        $sub = $cycle->subscription;
        $metadata = (array) ($sub->metadata ?? []);
        $forceFail = (bool) ($metadata['force_fail_billing'] ?? false);

        if ($forceFail) {
            return new BillingResult(
                success: false,
                amountCents: $cycle->planned_amount_cents,
                currency: $sub->billing_currency,
                error: 'mock_forced_failure',
                provider: 'mock',
            );
        }

        return new BillingResult(
            success: true,
            amountCents: $cycle->planned_amount_cents,
            currency: $sub->billing_currency,
            reference: 'mock_ch_' . Str::lower(Str::random(20)),
            raw: ['driver' => 'mock', 'cycle_id' => $cycle->id],
            provider: 'mock',
        );
    }
}

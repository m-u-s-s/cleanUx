<?php

namespace App\Services\SubscriptionsV2\Contracts;

use App\Models\SubscriptionsV2\SubscriptionCycleV2;
use App\Services\SubscriptionsV2\BillingResult;

interface BillingProviderContract
{
    public function name(): string;

    /** Charge un cycle. */
    public function chargeCycle(SubscriptionCycleV2 $cycle): BillingResult;
}

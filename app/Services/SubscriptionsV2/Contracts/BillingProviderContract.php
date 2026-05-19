<?php

namespace App\Services\SubscriptionsV2\Contracts;

use App\Models\SubscriptionsV2\SubscriptionCycleV2;
use App\Services\SubscriptionsV2\BillingResult;

interface BillingProviderContract
{
    public function name(): string;

    /**
     * Charge un cycle. Doit être idempotent : si déjà chargé, retourne success
     * avec le même reference. Soft-fail : capture les erreurs et retourne
     * BillingResult avec success=false + error.
     */
    public function chargeCycle(SubscriptionCycleV2 $cycle): BillingResult;
}

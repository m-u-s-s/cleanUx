<?php

namespace App\Events\Loyalty;

use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTier;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoyaltyTierUpgraded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public LoyaltyAccount $account,
        public ?LoyaltyTier $previousTier,
        public LoyaltyTier $newTier,
    ) {}
}

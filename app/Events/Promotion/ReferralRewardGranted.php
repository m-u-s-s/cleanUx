<?php

namespace App\Events\Promotion;

use App\Models\ReferralReward;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReferralRewardGranted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public ReferralReward $reward)
    {
    }
}

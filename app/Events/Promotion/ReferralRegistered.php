<?php

namespace App\Events\Promotion;

use App\Models\Referral;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReferralRegistered
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Referral $referral)
    {
    }
}

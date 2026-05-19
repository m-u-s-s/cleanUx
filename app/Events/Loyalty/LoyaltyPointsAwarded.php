<?php

namespace App\Events\Loyalty;

use App\Models\LoyaltyTransaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoyaltyPointsAwarded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public LoyaltyTransaction $transaction)
    {
    }
}

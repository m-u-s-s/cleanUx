<?php

namespace App\Events\Promotion;

use App\Models\PromoCodeRedemption;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PromoCodeRedeemed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public PromoCodeRedemption $redemption)
    {
    }
}

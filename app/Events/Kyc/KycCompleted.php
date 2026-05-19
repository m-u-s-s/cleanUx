<?php

namespace App\Events\Kyc;

use App\Models\KycVerification;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KycCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public KycVerification $verification)
    {
    }
}

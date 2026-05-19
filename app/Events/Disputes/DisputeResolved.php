<?php

namespace App\Events\Disputes;

use App\Models\ComplaintCase;
use App\Models\DisputeResolution;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DisputeResolved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public ComplaintCase $case, public DisputeResolution $resolution)
    {
    }
}

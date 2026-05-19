<?php

namespace App\Events\Disputes;

use App\Models\DisputeEvent as DisputeEventModel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DisputeMessageAdded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public DisputeEventModel $event)
    {
    }
}

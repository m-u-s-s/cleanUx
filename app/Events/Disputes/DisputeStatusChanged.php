<?php

namespace App\Events\Disputes;

use App\Models\ComplaintCase;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DisputeStatusChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public ComplaintCase $case,
        public string $fromStatus,
        public string $toStatus,
    ) {
    }
}

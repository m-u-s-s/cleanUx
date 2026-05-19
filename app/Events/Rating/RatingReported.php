<?php

namespace App\Events\Rating;

use App\Models\RatingReport;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RatingReported
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public RatingReport $report)
    {
    }
}

<?php

namespace App\Events\Rating;

use App\Models\Feedback;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RatingPublished
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Feedback $feedback)
    {
    }
}

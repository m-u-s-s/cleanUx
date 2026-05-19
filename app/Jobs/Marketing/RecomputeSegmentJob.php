<?php

namespace App\Jobs\Marketing;

use App\Models\MarketingSegment;
use App\Services\Marketing\SegmentEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecomputeSegmentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;
    public int $tries = 2;

    public function __construct(public int $segmentId)
    {
    }

    public function handle(SegmentEngine $engine): void
    {
        $segment = MarketingSegment::find($this->segmentId);
        if (! $segment) {
            return;
        }
        $engine->compute($segment);
    }
}

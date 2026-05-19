<?php

namespace App\Jobs\Audit;

use App\Services\Audit\AuditRetentionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PurgeAuditEventsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;

    public function handle(AuditRetentionService $svc): void
    {
        $count = $svc->purge();
        Log::info('PurgeAuditEventsJob: purged', ['count' => $count]);
    }
}

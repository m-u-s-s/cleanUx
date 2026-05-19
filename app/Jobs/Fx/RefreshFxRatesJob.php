<?php

namespace App\Jobs\Fx;

use App\Services\Fx\FxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshFxRatesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 60;
    public int $tries = 2;

    public function __construct(public ?string $base = null)
    {
    }

    public function handle(FxService $svc): void
    {
        $svc->refreshAll($this->base);
    }
}

<?php

namespace App\Jobs\Marketing;

use App\Models\MarketingCampaignRecipient;
use App\Services\Marketing\CampaignEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchCampaignStepJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 60;
    public int $tries = 3;

    public function __construct(public int $recipientId)
    {
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(CampaignEngine $engine): void
    {
        $recipient = MarketingCampaignRecipient::find($this->recipientId);
        if (! $recipient) {
            return;
        }
        $engine->dispatchOne($recipient);
    }
}

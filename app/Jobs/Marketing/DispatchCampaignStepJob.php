<?php

namespace App\Jobs\Marketing;

use App\Models\MarketingCampaignRecipient;
use App\Services\Marketing\CampaignEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * DispatchCampaignStepJob — two modes:
 *
 *  - Scheduled (no recipientId): processes all due recipients via CampaignEngine::dispatchDueRecipients().
 *    Called every 10 min by Kernel::schedule().
 *
 *  - Per-recipient (recipientId provided): dispatches one specific recipient.
 *    Useful for targeted re-sends.
 */
class DispatchCampaignStepJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    /** @param int|null $recipientId  null = bulk drip mode; int = single recipient mode */
    public function __construct(public ?int $recipientId = null) {}

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(CampaignEngine $engine): void
    {
        if ($this->recipientId !== null) {
            // Single-recipient mode
            $recipient = MarketingCampaignRecipient::find($this->recipientId);
            if (! $recipient) {
                return;
            }
            $engine->dispatchOne($recipient);

            return;
        }

        // Bulk drip mode — process all due recipients
        $sent = $engine->dispatchDueRecipients(limit: 200);
        Log::info("DispatchCampaignStepJob: dispatched {$sent} recipients (drip run)");
    }
}

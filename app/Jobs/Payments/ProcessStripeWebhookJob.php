<?php

namespace App\Jobs\Payments;

use App\Models\StripeWebhookEvent;
use App\Services\Payments\Webhooks\StripeWebhookEventProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessStripeWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(public int $eventId)
    {
    }

    public function handle(StripeWebhookEventProcessor $processor): void
    {
        $event = StripeWebhookEvent::find($this->eventId);
        if (! $event) {
            return;
        }

        $processor->process($event);
    }

    /**
     * Backoff exponentiel pour retry de la queue (1m, 5m, 15m, 1h, 6h).
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600, 21600];
    }
}

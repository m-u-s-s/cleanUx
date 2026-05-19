<?php

namespace App\Jobs\Insurance;

use App\Models\InsuranceWebhookEvent;
use App\Services\Insurance\InsuranceProviderInterface;
use App\Services\Insurance\InsuranceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessInsuranceWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(public int $eventId)
    {
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(InsuranceProviderInterface $provider, InsuranceService $service): void
    {
        $event = InsuranceWebhookEvent::find($this->eventId);
        if (! $event || $event->status === InsuranceWebhookEvent::STATUS_PROCESSED) {
            return;
        }

        $event->increment('attempts');

        try {
            $update = $provider->mapWebhookEvent($event->payload ?? []);
            if (! $update) {
                $event->update([
                    'status' => InsuranceWebhookEvent::STATUS_IGNORED,
                    'processed_at' => now(),
                ]);
                return;
            }

            $target = $service->applyWebhookUpdate($update);

            $event->update([
                'status' => $target ? InsuranceWebhookEvent::STATUS_PROCESSED : InsuranceWebhookEvent::STATUS_IGNORED,
                'processed_at' => now(),
                'last_error' => null,
            ]);
        } catch (\Throwable $e) {
            $event->update([
                'status' => InsuranceWebhookEvent::STATUS_FAILED,
                'last_error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

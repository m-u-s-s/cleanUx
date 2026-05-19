<?php

namespace App\Jobs\Kyc;

use App\Models\KycWebhookEvent;
use App\Services\Kyc\KycVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessKycWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(public int $eventId)
    {
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(KycVerificationService $service): void
    {
        $event = KycWebhookEvent::find($this->eventId);
        if (! $event || $event->status === KycWebhookEvent::STATUS_PROCESSED) {
            return;
        }

        $event->increment('attempts');

        try {
            $verification = $service->applyWebhookPayload($event->payload ?? []);

            $event->update([
                'status' => $verification
                    ? KycWebhookEvent::STATUS_PROCESSED
                    : KycWebhookEvent::STATUS_IGNORED,
                'processed_at' => now(),
                'last_error' => null,
            ]);
        } catch (\Throwable $e) {
            $event->update([
                'status' => KycWebhookEvent::STATUS_FAILED,
                'last_error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

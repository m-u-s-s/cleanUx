<?php

namespace App\Jobs\Sms;

use App\Models\SmsWebhookEvent;
use App\Services\Notifications\SmsService;
use App\Services\Sms\SmsProviderInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessSmsWebhookJob implements ShouldQueue
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

    public function handle(SmsProviderInterface $provider, SmsService $smsService): void
    {
        $event = SmsWebhookEvent::find($this->eventId);
        if (! $event || $event->status === SmsWebhookEvent::STATUS_PROCESSED) {
            return;
        }

        $event->increment('attempts');

        try {
            $update = $provider->mapWebhookEvent($event->payload ?? []);

            if (! $update) {
                $event->update([
                    'status' => SmsWebhookEvent::STATUS_IGNORED,
                    'processed_at' => now(),
                ]);
                return;
            }

            $message = $smsService->applyStatusUpdate($update);

            $event->update([
                'status' => $message
                    ? SmsWebhookEvent::STATUS_PROCESSED
                    : SmsWebhookEvent::STATUS_IGNORED,
                'processed_at' => now(),
                'last_error' => null,
            ]);
        } catch (\Throwable $e) {
            $event->update([
                'status' => SmsWebhookEvent::STATUS_FAILED,
                'last_error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

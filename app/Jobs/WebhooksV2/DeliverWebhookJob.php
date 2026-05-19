<?php

namespace App\Jobs\WebhooksV2;

use App\Models\WebhookDelivery;
use App\Services\WebhooksV2\WebhookDeliveryRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;  // retries gérés en interne par WebhookDeliveryRunner via next_retry_at
    public int $timeout = 60;

    public function __construct(public int $deliveryId) {}

    public function handle(WebhookDeliveryRunner $runner): void
    {
        $delivery = WebhookDelivery::query()->find($this->deliveryId);
        if (! $delivery || $delivery->isTerminal()) {
            return;
        }
        $runner->run($delivery);
    }
}

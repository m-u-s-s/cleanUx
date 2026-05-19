<?php

namespace App\Services\WebhooksV2;

use App\Jobs\WebhooksV2\DeliverWebhookJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\Models\WebhookSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookDispatcher
{
    /**
     * Persist event + fanout deliveries for every active subscription matching event_code.
     * Idempotent : if idempotency_key given and already used, returns the existing event.
     *
     * @return WebhookEvent|null  null if event not whitelisted or feature disabled
     */
    public function emit(
        string $eventCode,
        array $payload,
        ?string $idempotencyKey = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?Carbon $occurredAt = null,
    ): ?WebhookEvent {
        if (! config('webhooks_v2.enabled', true)) {
            return null;
        }
        $allowed = (array) config('webhooks_v2.allowed_events', []);
        if (! in_array($eventCode, $allowed, true)) {
            Log::info('[webhooks_v2] event ignored (not whitelisted)', ['event_code' => $eventCode]);
            return null;
        }

        if ($idempotencyKey) {
            $existing = WebhookEvent::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        $event = DB::transaction(function () use ($eventCode, $payload, $idempotencyKey, $sourceType, $sourceId, $occurredAt) {
            $event = WebhookEvent::query()->create([
                'event_id' => WebhookEvent::generateEventId(),
                'event_code' => $eventCode,
                'payload' => $payload,
                'idempotency_key' => $idempotencyKey,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'occurred_at' => $occurredAt ?? now(),
            ]);

            $subs = WebhookSubscription::query()
                ->where('event_code', $eventCode)
                ->where('is_active', true)
                ->get();

            foreach ($subs as $sub) {
                $endpoint = WebhookEndpoint::query()->find($sub->endpoint_id);
                if (! $endpoint || ! $endpoint->isDeliverable()) {
                    continue;
                }
                if (! $this->passesFilters($sub, $payload)) {
                    continue;
                }
                WebhookDelivery::query()->create([
                    'event_id' => $event->id,
                    'endpoint_id' => $endpoint->id,
                    'status' => WebhookDelivery::STATUS_PENDING,
                    'attempt' => 0,
                    'max_attempts' => $endpoint->max_attempts ?: (int) config('webhooks_v2.max_attempts', 6),
                ]);
            }

            return $event;
        });

        // dispatch jobs out of transaction
        $deliveries = WebhookDelivery::query()->where('event_id', $event->id)->get();
        foreach ($deliveries as $delivery) {
            DeliverWebhookJob::dispatch($delivery->id)
                ->onQueue((string) config('webhooks_v2.queue', 'webhooks'));
        }

        return $event;
    }

    /**
     * Re-enqueue a specific delivery (admin replay).
     */
    public function replay(WebhookDelivery $delivery): WebhookDelivery
    {
        $delivery->update([
            'status' => WebhookDelivery::STATUS_PENDING,
            'next_retry_at' => null,
            'last_error' => null,
        ]);
        DeliverWebhookJob::dispatch($delivery->id)
            ->onQueue((string) config('webhooks_v2.queue', 'webhooks'));
        return $delivery->fresh();
    }

    private function passesFilters(WebhookSubscription $sub, array $payload): bool
    {
        $filters = (array) ($sub->filters ?? []);
        if (empty($filters)) {
            return true;
        }
        foreach ($filters as $key => $expected) {
            $actual = data_get($payload, $key);
            if (is_array($expected)) {
                if (! in_array($actual, $expected, true)) {
                    return false;
                }
            } elseif ($actual !== $expected) {
                return false;
            }
        }
        return true;
    }
}

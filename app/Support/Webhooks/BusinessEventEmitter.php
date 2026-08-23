<?php

namespace App\Support\Webhooks;

use App\Services\WebhooksV2\WebhookDispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/** Helper façade pour émettre des webhook events business depuis les Observers / Services / Controllers, sans risque de crasher le flow appelant. */
class BusinessEventEmitter
{
    public static function emit(
        string $eventCode,
        array $payload,
        ?string $idempotencyKey = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
    ): void {
        try {
            if (! Schema::hasTable('webhook_events')) {
                return;
            }
            if (! (bool) config('webhooks_v2.enabled', true)) {
                return;
            }
            app(WebhookDispatcher::class)->emit(
                eventCode: $eventCode,
                payload: $payload,
                idempotencyKey: $idempotencyKey,
                sourceType: $sourceType,
                sourceId: $sourceId,
            );
        } catch (\Throwable $e) {
            Log::warning('[business_webhook] emit failed', [
                'event' => $eventCode,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

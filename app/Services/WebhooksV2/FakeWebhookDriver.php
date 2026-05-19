<?php

namespace App\Services\WebhooksV2;

use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;

/**
 * In-memory recorder for tests. Activated when config('webhooks_v2.driver') === 'fake'.
 */
class FakeWebhookDriver
{
    /** @var array<int, array{endpoint_id:int,event_id:int,body:string,headers:array}> */
    protected static array $deliveries = [];

    public static function record(WebhookEndpoint $endpoint, WebhookEvent $event, string $body, array $headers): void
    {
        self::$deliveries[] = [
            'endpoint_id' => $endpoint->id,
            'event_id' => $event->id,
            'event_code' => $event->event_code,
            'body' => $body,
            'headers' => $headers,
        ];
    }

    public static function all(): array
    {
        return self::$deliveries;
    }

    public static function flush(): void
    {
        self::$deliveries = [];
    }

    public static function countFor(int $endpointId): int
    {
        return count(array_filter(self::$deliveries, fn ($d) => $d['endpoint_id'] === $endpointId));
    }
}

<?php

namespace App\Services\WebhooksV2;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookDeliveryRunner
{
    public function __construct(protected WebhookSigner $signer) {}

    /**
     * Attempt to deliver one webhook delivery. Updates the row and returns it.
     */
    public function run(WebhookDelivery $delivery): WebhookDelivery
    {
        if ($delivery->isTerminal()) {
            return $delivery;
        }

        $event = WebhookEvent::query()->find($delivery->event_id);
        $endpoint = WebhookEndpoint::query()->find($delivery->endpoint_id);
        if (! $event || ! $endpoint) {
            $delivery->update([
                'status' => WebhookDelivery::STATUS_CANCELLED,
                'last_error' => 'event or endpoint missing',
            ]);
            return $delivery->fresh();
        }
        if (! $endpoint->isDeliverable()) {
            $delivery->update([
                'status' => WebhookDelivery::STATUS_CANCELLED,
                'last_error' => 'endpoint inactive or suspended',
            ]);
            return $delivery->fresh();
        }

        $body = $this->buildBody($event, $delivery);
        $timestamp = time();
        $signature = $this->signer->sign($body, $endpoint->secret, $timestamp);
        $idempotencyKey = $event->event_id . ':' . $endpoint->id;
        $sigHeaderName = (string) config('webhooks_v2.signature_header', 'X-CleanUx-Signature');

        $headers = array_merge(
            [
                'Content-Type' => 'application/json',
                'User-Agent' => 'CleanUx-Webhooks/1.0',
                'X-CleanUx-Event' => $event->event_code,
                'X-CleanUx-Event-Id' => $event->event_id,
                'X-CleanUx-Idempotency-Key' => $idempotencyKey,
                'X-CleanUx-Delivery-Attempt' => (string) ($delivery->attempt + 1),
                $sigHeaderName => $signature,
            ],
            (array) ($endpoint->headers ?? [])
        );

        $delivery->update([
            'status' => WebhookDelivery::STATUS_IN_FLIGHT,
            'attempt' => $delivery->attempt + 1,
            'last_attempted_at' => now(),
            'signature_sent' => $signature,
            'idempotency_key_sent' => $idempotencyKey,
        ]);

        $start = microtime(true);
        $status = null;
        $responseBody = null;
        $errorMessage = null;
        $isOk = false;

        try {
            if ((string) config('webhooks_v2.driver', 'real') === 'fake') {
                FakeWebhookDriver::record($endpoint, $event, $body, $headers);
                $status = 200;
                $responseBody = '{"ok":true,"driver":"fake"}';
                $isOk = true;
            } else {
                $response = Http::withHeaders($headers)
                    ->timeout((int) ($endpoint->timeout_seconds ?: config('webhooks_v2.timeout_seconds', 15)))
                    ->connectTimeout((int) config('webhooks_v2.connect_timeout_seconds', 5))
                    ->withBody($body, 'application/json')
                    ->post($endpoint->url);
                $status = $response->status();
                $responseBody = $this->truncate((string) $response->body());
                $isOk = $response->successful();
            }
        } catch (\Throwable $e) {
            $errorMessage = $this->truncate($e->getMessage());
            Log::warning('[webhooks_v2] delivery http error', [
                'delivery_id' => $delivery->id,
                'endpoint_id' => $endpoint->id,
                'error' => $errorMessage,
            ]);
        }

        $latencyMs = (int) round((microtime(true) - $start) * 1000);

        if ($isOk) {
            $delivery->update([
                'status' => WebhookDelivery::STATUS_DELIVERED,
                'delivered_at' => now(),
                'last_response_status' => $status,
                'last_response_body' => $responseBody,
                'last_error' => null,
                'last_latency_ms' => $latencyMs,
                'next_retry_at' => null,
            ]);
            $endpoint->update([
                'last_success_at' => now(),
                'consecutive_failures' => 0,
            ]);
            return $delivery->fresh();
        }

        // failure path
        $nextAttempt = $delivery->attempt;
        $isLast = $nextAttempt >= $delivery->max_attempts;
        $delivery->update([
            'status' => $isLast ? WebhookDelivery::STATUS_DEAD : WebhookDelivery::STATUS_FAILED,
            'last_response_status' => $status,
            'last_response_body' => $responseBody,
            'last_error' => $errorMessage ?: ('http_' . ($status ?? 'unknown')),
            'last_latency_ms' => $latencyMs,
            'next_retry_at' => $isLast ? null : $this->computeNextRetryAt($nextAttempt),
        ]);
        $consecutive = $endpoint->consecutive_failures + 1;
        $autoSuspendAt = (int) config('webhooks_v2.auto_suspend_after_failures', 25);
        $shouldSuspend = $autoSuspendAt > 0 && $consecutive >= $autoSuspendAt;
        $endpoint->update([
            'last_failure_at' => now(),
            'consecutive_failures' => $consecutive,
            'is_suspended' => $shouldSuspend ? true : $endpoint->is_suspended,
            'suspension_reason' => $shouldSuspend
                ? 'auto-suspended after ' . $consecutive . ' consecutive failures'
                : $endpoint->suspension_reason,
        ]);

        return $delivery->fresh();
    }

    private function buildBody(WebhookEvent $event, WebhookDelivery $delivery): string
    {
        return (string) json_encode([
            'id' => $event->event_id,
            'event' => $event->event_code,
            'occurred_at' => optional($event->occurred_at)->toIso8601String(),
            'attempt' => $delivery->attempt + 1,
            'data' => $event->payload,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function computeNextRetryAt(int $attempt): Carbon
    {
        $schedule = (array) config('webhooks_v2.backoff_schedule_seconds', [30, 120, 600, 1800, 7200, 21600]);
        $idx = max(0, min($attempt - 1, count($schedule) - 1));
        $delay = (int) ($schedule[$idx] ?? 1800);
        return now()->addSeconds($delay);
    }

    private function truncate(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }
        $max = (int) config('webhooks_v2.response_body_max_length', 4096);
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max) : $text;
    }
}

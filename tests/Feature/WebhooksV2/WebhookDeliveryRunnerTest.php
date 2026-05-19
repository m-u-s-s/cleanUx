<?php

namespace Tests\Feature\WebhooksV2;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\Services\WebhooksV2\WebhookDeliveryRunner;
use App\Services\WebhooksV2\WebhookSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookDeliveryRunnerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('webhooks_v2.enabled', true);
        Config::set('webhooks_v2.driver', 'real');
        Config::set('webhooks_v2.backoff_schedule_seconds', [30, 120, 600, 1800, 7200, 21600]);
        Config::set('webhooks_v2.auto_suspend_after_failures', 5);
        Config::set('webhooks_v2.signature_algo', 'sha256');
        Config::set('webhooks_v2.signature_version', 'v1');
        Config::set('webhooks_v2.signature_header', 'X-CleanUx-Signature');
    }

    private function makePair(array $epOverrides = [], array $eventOverrides = []): array
    {
        $ep = WebhookEndpoint::query()->create(array_merge([
            'code' => 'whe_run_' . uniqid(),
            'name' => 'Runner EP',
            'url' => 'https://target.test/hook',
            'secret' => 'whsec_runner',
            'is_active' => true,
            'max_attempts' => 6,
            'timeout_seconds' => 10,
        ], $epOverrides));

        $event = WebhookEvent::query()->create(array_merge([
            'event_id' => 'evt_run_' . uniqid(),
            'event_code' => 'booking.created',
            'payload' => ['booking_id' => 7],
            'occurred_at' => now(),
        ], $eventOverrides));

        $delivery = WebhookDelivery::query()->create([
            'event_id' => $event->id,
            'endpoint_id' => $ep->id,
            'status' => WebhookDelivery::STATUS_PENDING,
            'attempt' => 0,
            'max_attempts' => $ep->max_attempts,
        ]);

        return [$ep, $event, $delivery];
    }

    public function test_successful_2xx_marks_delivered_and_signs_payload(): void
    {
        Http::fake([
            'target.test/*' => Http::response(['ok' => true], 200),
        ]);
        [$ep, $event, $delivery] = $this->makePair();

        $runner = new WebhookDeliveryRunner(new WebhookSigner());
        $result = $runner->run($delivery);

        $this->assertSame(WebhookDelivery::STATUS_DELIVERED, $result->status);
        $this->assertSame(200, $result->last_response_status);
        $this->assertSame(1, $result->attempt);
        $this->assertNotNull($result->delivered_at);
        $this->assertNotNull($result->signature_sent);
        $this->assertStringStartsWith('t=', $result->signature_sent);

        Http::assertSent(function ($req) use ($ep, $event) {
            return $req->url() === $ep->url
                && $req->hasHeader('X-CleanUx-Event', $event->event_code)
                && $req->hasHeader('X-CleanUx-Event-Id', $event->event_id)
                && $req->hasHeader('X-CleanUx-Signature');
        });
    }

    public function test_failure_5xx_marks_failed_with_next_retry(): void
    {
        Http::fake([
            'target.test/*' => Http::response('boom', 502),
        ]);
        [$ep, , $delivery] = $this->makePair();

        $result = (new WebhookDeliveryRunner(new WebhookSigner()))->run($delivery);

        $this->assertSame(WebhookDelivery::STATUS_FAILED, $result->status);
        $this->assertSame(502, $result->last_response_status);
        $this->assertNotNull($result->next_retry_at);
        $this->assertTrue($result->next_retry_at->greaterThan(now()));
        $this->assertSame(1, $ep->fresh()->consecutive_failures);
    }

    public function test_last_attempt_failure_marks_dead(): void
    {
        Http::fake([
            'target.test/*' => Http::response('err', 500),
        ]);
        [$ep, , $delivery] = $this->makePair(['max_attempts' => 2]);
        $delivery->update(['attempt' => 1, 'max_attempts' => 2]);

        $result = (new WebhookDeliveryRunner(new WebhookSigner()))->run($delivery);

        $this->assertSame(WebhookDelivery::STATUS_DEAD, $result->status);
        $this->assertNull($result->next_retry_at);
    }

    public function test_auto_suspends_endpoint_after_consecutive_failures(): void
    {
        Http::fake([
            'target.test/*' => Http::response('err', 500),
        ]);
        [$ep] = $this->makePair(['max_attempts' => 10]);
        $ep->update(['consecutive_failures' => 4]);  // next failure = 5 = auto_suspend
        $event = WebhookEvent::query()->create([
            'event_id' => 'evt_susp', 'event_code' => 'booking.created',
            'payload' => [], 'occurred_at' => now(),
        ]);
        $delivery = WebhookDelivery::query()->create([
            'event_id' => $event->id, 'endpoint_id' => $ep->id,
            'status' => WebhookDelivery::STATUS_PENDING, 'attempt' => 0, 'max_attempts' => 10,
        ]);

        (new WebhookDeliveryRunner(new WebhookSigner()))->run($delivery);

        $this->assertTrue($ep->fresh()->is_suspended);
        $this->assertStringContainsString('auto-suspended', (string) $ep->fresh()->suspension_reason);
    }

    public function test_cancels_when_endpoint_suspended(): void
    {
        [$ep, , $delivery] = $this->makePair(['is_suspended' => true]);

        $result = (new WebhookDeliveryRunner(new WebhookSigner()))->run($delivery);

        $this->assertSame(WebhookDelivery::STATUS_CANCELLED, $result->status);
        $this->assertStringContainsString('suspended', (string) $result->last_error);
    }

    public function test_signature_is_valid_via_signer_verify(): void
    {
        Http::fake([
            'target.test/*' => Http::response('', 200),
        ]);
        [$ep, , $delivery] = $this->makePair();
        $signer = new WebhookSigner();
        $runner = new WebhookDeliveryRunner($signer);
        $result = $runner->run($delivery);

        $captured = null;
        Http::assertSent(function ($req) use (&$captured) {
            $captured = ['body' => $req->body(), 'sig' => $req->header('X-CleanUx-Signature')[0] ?? null];
            return true;
        });

        $this->assertNotNull($captured);
        $this->assertTrue($signer->verify($captured['body'], $captured['sig'], $ep->secret));
    }
}

<?php

namespace Tests\Unit\Alerts;

use App\Events\BusinessAlertRaised;
use App\Listeners\Alerts\BusinessAlertSentryListener;
use PHPUnit\Framework\Attributes\Test;
use Sentry\State\Scope;
use Tests\TestCase;

/** Tests for BusinessAlertSentryListener. */
class BusinessAlertSentryListenerTest extends TestCase
{
    #[Test]
    public function it_calls_capture_message_with_the_alert_key_when_sentry_is_bound(): void
    {
        // ── Arrange ──────────────────────────────────────────────────────────
        $captured = [];

        $spy = new class($captured)
        {
            /** @param array<int, array{message: string, level: mixed}> $captured */
            public function __construct(private array &$captured) {}

            public function withScope(callable $callback): void
            {
                // Invoke the callback with a real Scope so the listener code
                // inside the closure (setContext / setTag) runs without errors.
                $callback(new Scope);
            }

            public function captureMessage(string $message, mixed $level = null): void
            {
                $this->captured[] = ['message' => $message, 'level' => $level];
            }
        };

        app()->instance('sentry', $spy);

        $event = new BusinessAlertRaised(
            level: 'critical',
            key: 'payment_capture_failed',
            message: 'Payment capture failed for booking #42',
            context: ['booking_id' => 42],
        );

        // ── Act ───────────────────────────────────────────────────────────────
        (new BusinessAlertSentryListener)->handle($event);

        // ── Assert ────────────────────────────────────────────────────────────
        $this->assertCount(1, $captured, 'captureMessage should be called exactly once');

        $call = $captured[0];
        $this->assertStringContainsString(
            'payment_capture_failed',
            $call['message'],
            'Captured message must contain the alert key',
        );

        // Severity::fatal() __toString === 'fatal' for a critical-level alert
        $this->assertSame('fatal', (string) $call['level']);
    }

    #[Test]
    public function it_maps_non_critical_level_to_sentry_error_severity(): void
    {
        $captured = [];

        $spy = new class($captured)
        {
            public function __construct(private array &$captured) {}

            public function withScope(callable $callback): void
            {
                $callback(new Scope);
            }

            public function captureMessage(string $message, mixed $level = null): void
            {
                $this->captured[] = ['message' => $message, 'level' => $level];
            }
        };

        app()->instance('sentry', $spy);

        $event = new BusinessAlertRaised(
            level: 'error',
            key: 'some_error_key',
            message: 'Something failed',
            context: [],
        );

        (new BusinessAlertSentryListener)->handle($event);

        $this->assertCount(1, $captured);
        $this->assertSame('error', (string) $captured[0]['level']);
    }

    #[Test]
    public function it_does_nothing_when_sentry_is_not_bound(): void
    {
        // Ensure sentry is not in the container
        app()->forgetInstance('sentry');

        // Forcibly remove any binding (in case it was bound as a singleton)
        if (app()->bound('sentry')) {
            $this->markTestSkipped('sentry is bound via a service provider in this environment; skip no-op path test.');
        }

        $event = new BusinessAlertRaised(
            level: 'critical',
            key: 'payout_failed',
            message: 'Payout failed',
            context: [],
        );

        // The listener must not throw when sentry is absent
        $exception = null;

        try {
            (new BusinessAlertSentryListener)->handle($event);
        } catch (\Throwable $e) {
            $exception = $e;
        }

        $this->assertNull($exception, 'Listener must be a no-op and not throw when sentry is unbound');
    }
}

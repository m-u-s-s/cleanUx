<?php

namespace Tests\Feature\Payments;

use App\Jobs\Payments\ProcessStripeWebhookJob;
use App\Models\StripeWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/** M9 — failed Stripe webhook events must be re-dispatched when due, so transient failures are not lost forever (the queue itself never retries: tries=1). */
class RetryFailedStripeWebhooksTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(array $overrides = []): StripeWebhookEvent
    {
        return StripeWebhookEvent::create(array_merge([
            'stripe_event_id' => 'evt_'.uniqid(),
            'type' => 'payout.failed',
            'status' => StripeWebhookEvent::STATUS_FAILED,
            'payload' => ['data' => ['object' => ['id' => 'po_x']]],
            'attempts' => 1,
            'max_attempts' => 5,
            'next_retry_at' => now()->subMinute(),
            'received_at' => now()->subHour(),
        ], $overrides));
    }

    public function test_redispatches_failed_events_due_for_retry(): void
    {
        Bus::fake();
        $event = $this->makeEvent();

        $this->artisan('stripe:retry-failed-webhooks')->assertSuccessful();

        Bus::assertDispatched(ProcessStripeWebhookJob::class, fn ($job) => $job->eventId === $event->id);
    }

    public function test_does_not_redispatch_events_not_yet_due(): void
    {
        Bus::fake();
        $this->makeEvent(['next_retry_at' => now()->addHour()]);

        $this->artisan('stripe:retry-failed-webhooks')->assertSuccessful();

        Bus::assertNotDispatched(ProcessStripeWebhookJob::class);
    }

    public function test_does_not_redispatch_events_that_exhausted_attempts(): void
    {
        Bus::fake();
        $this->makeEvent(['attempts' => 5, 'max_attempts' => 5]);

        $this->artisan('stripe:retry-failed-webhooks')->assertSuccessful();

        Bus::assertNotDispatched(ProcessStripeWebhookJob::class);
    }
}

<?php

namespace Tests\Feature\Payments;

use App\Models\StripeWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StripeWebhookIdempotenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_storing_same_event_id_twice_creates_only_one_row(): void
    {
        Queue::fake();

        $event1 = StripeWebhookEvent::firstOrCreate(
            ['stripe_event_id' => 'evt_test_123'],
            [
                'type' => 'payment_intent.succeeded',
                'status' => StripeWebhookEvent::STATUS_RECEIVED,
                'payload' => ['data' => ['object' => ['id' => 'pi_test']]],
                'received_at' => now(),
            ]
        );

        $event2 = StripeWebhookEvent::firstOrCreate(
            ['stripe_event_id' => 'evt_test_123'],
            [
                'type' => 'payment_intent.succeeded',
                'status' => StripeWebhookEvent::STATUS_RECEIVED,
                'payload' => ['data' => ['object' => ['id' => 'pi_test']]],
                'received_at' => now(),
            ]
        );

        $this->assertSame($event1->id, $event2->id);
        $this->assertSame(1, StripeWebhookEvent::count());
    }

    public function test_dead_letter_after_max_attempts(): void
    {
        $event = StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_fail_456',
            'type' => 'payment_intent.succeeded',
            'status' => StripeWebhookEvent::STATUS_FAILED,
            'payload' => ['data' => ['object' => ['id' => 'pi_unknown']]],
            'attempts' => 5,
            'max_attempts' => 5,
            'received_at' => now(),
            'last_error' => 'Some error',
        ]);

        $this->assertFalse($event->canRetry());
        $this->assertSame(5, $event->attempts);
    }

    public function test_due_for_retry_scope_finds_retryable(): void
    {
        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_a',
            'type' => 'x',
            'status' => StripeWebhookEvent::STATUS_FAILED,
            'attempts' => 1,
            'max_attempts' => 5,
            'payload' => [],
            'received_at' => now(),
            'next_retry_at' => now()->subMinute(),
        ]);

        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_b',
            'type' => 'x',
            'status' => StripeWebhookEvent::STATUS_FAILED,
            'attempts' => 5,
            'max_attempts' => 5,
            'payload' => [],
            'received_at' => now(),
        ]);

        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_c',
            'type' => 'x',
            'status' => StripeWebhookEvent::STATUS_FAILED,
            'attempts' => 1,
            'max_attempts' => 5,
            'payload' => [],
            'received_at' => now(),
            'next_retry_at' => now()->addHour(),
        ]);

        $due = StripeWebhookEvent::query()->dueForRetry()->get();
        $this->assertCount(1, $due);
        $this->assertSame('evt_a', $due->first()->stripe_event_id);
    }

    public function test_processor_marks_event_as_processed_when_handled(): void
    {
        $event = StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_processed_xyz',
            'type' => 'account.updated',
            'status' => StripeWebhookEvent::STATUS_RECEIVED,
            'payload' => ['data' => ['object' => ['id' => 'acct_unknown_xyz']]],
            'received_at' => now(),
        ]);

        $processor = app(\App\Services\Payments\Webhooks\StripeWebhookEventProcessor::class);
        $processor->process($event);

        $event->refresh();
        $this->assertContains($event->status, [
            StripeWebhookEvent::STATUS_PROCESSED,
            StripeWebhookEvent::STATUS_IGNORED,
        ]);
    }

    public function test_processor_is_noop_on_terminal_event(): void
    {
        $event = StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_done',
            'type' => 'account.updated',
            'status' => StripeWebhookEvent::STATUS_PROCESSED,
            'payload' => ['data' => ['object' => ['id' => 'acct_x']]],
            'received_at' => now(),
            'processed_at' => now(),
        ]);

        $before = (int) $event->fresh()->attempts;

        $processor = app(\App\Services\Payments\Webhooks\StripeWebhookEventProcessor::class);
        $processor->process($event);

        $this->assertSame($before, (int) $event->fresh()->attempts);
    }
}

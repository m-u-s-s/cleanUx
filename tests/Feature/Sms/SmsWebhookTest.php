<?php

namespace Tests\Feature\Sms;

use App\Jobs\Sms\ProcessSmsWebhookJob;
use App\Models\SmsMessage;
use App\Models\SmsWebhookEvent;
use App\Models\User;
use App\Services\Notifications\SmsService;
use App\Services\Sms\Providers\SmsMockProvider;
use App\Services\Sms\SmsProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SmsWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(SmsProviderInterface::class, SmsMockProvider::class);
    }

    public function test_unknown_provider_returns_404(): void
    {
        $this->postJson('/webhooks/sms/unknown', [])->assertStatus(404);
    }

    public function test_mock_webhook_stores_event_and_dispatches_job(): void
    {
        Queue::fake();

        $payload = [
            'external_id' => 'mock_sms_abc123',
            'status' => 'delivered',
            'event_type' => 'delivered',
        ];

        $response = $this->postJson('/webhooks/sms/mock', $payload);

        $response->assertOk();
        $response->assertJson(['ok' => true]);

        $this->assertSame(1, SmsWebhookEvent::count());
        $event = SmsWebhookEvent::first();
        $this->assertSame('mock', $event->provider);
        $this->assertSame('mock_sms_abc123', $event->external_event_id);

        Queue::assertPushed(ProcessSmsWebhookJob::class);
    }

    public function test_duplicate_webhook_event_id_is_not_redispatched(): void
    {
        Queue::fake();

        $payload = [
            'external_id' => 'dup_sms_event',
            'status' => 'delivered',
        ];

        $this->postJson('/webhooks/sms/mock', $payload);

        SmsWebhookEvent::where('external_event_id', 'dup_sms_event')
            ->update(['status' => SmsWebhookEvent::STATUS_PROCESSED]);

        $this->postJson('/webhooks/sms/mock', $payload);

        $this->assertSame(1, SmsWebhookEvent::count());
        Queue::assertPushed(ProcessSmsWebhookJob::class, 1);
    }

    public function test_processing_webhook_applies_status_to_matching_message(): void
    {
        $user = User::factory()->client()->create();

        $sms = app(SmsService::class)->dispatch(
            toPhone: '+32412345678',
            body: 'pending dlr',
            user: $user,
        );

        $event = SmsWebhookEvent::create([
            'provider' => 'mock',
            'external_event_id' => 'evt_' . $sms->external_id,
            'event_type' => 'delivered',
            'payload' => [
                'external_id' => $sms->external_id,
                'status' => SmsMessage::STATUS_DELIVERED,
            ],
            'status' => SmsWebhookEvent::STATUS_RECEIVED,
            'received_at' => now(),
        ]);

        (new ProcessSmsWebhookJob($event->id))
            ->handle(app(SmsProviderInterface::class), app(SmsService::class));

        $event->refresh();
        $this->assertSame(SmsWebhookEvent::STATUS_PROCESSED, $event->status);

        $sms->refresh();
        $this->assertSame(SmsMessage::STATUS_DELIVERED, $sms->status);
        $this->assertNotNull($sms->delivered_at);
    }

    public function test_processing_webhook_with_unknown_message_marks_ignored(): void
    {
        $event = SmsWebhookEvent::create([
            'provider' => 'mock',
            'external_event_id' => 'orphan_evt',
            'event_type' => 'delivered',
            'payload' => [
                'external_id' => 'mock_sms_never_existed',
                'status' => 'delivered',
            ],
            'status' => SmsWebhookEvent::STATUS_RECEIVED,
            'received_at' => now(),
        ]);

        (new ProcessSmsWebhookJob($event->id))
            ->handle(app(SmsProviderInterface::class), app(SmsService::class));

        $event->refresh();
        $this->assertSame(SmsWebhookEvent::STATUS_IGNORED, $event->status);
    }
}

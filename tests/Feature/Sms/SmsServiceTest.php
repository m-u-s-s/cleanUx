<?php

namespace Tests\Feature\Sms;

use App\Models\SmsMessage;
use App\Models\User;
use App\Services\Notifications\SmsService;
use App\Services\Sms\Providers\SmsMockProvider;
use App\Services\Sms\SmsProviderInterface;
use App\Services\Sms\SmsStatusUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SmsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(SmsProviderInterface::class, SmsMockProvider::class);
        Config::set('sms.enabled', true);
        Config::set('sms.rate_limits.per_phone_per_hour', 5);
        Config::set('sms.rate_limits.per_phone_per_day', 20);
        Config::set('sms.rate_limits.per_user_per_hour', 10);
    }

    public function test_dispatch_creates_sms_message_with_mock_provider(): void
    {
        $user = User::factory()->client()->create();

        $message = app(SmsService::class)->dispatch(
            toPhone: '+32412345678',
            body: 'Bonjour, ceci est un test.',
            user: $user,
        );

        $this->assertNotNull($message);
        $this->assertSame('mock', $message->provider);
        $this->assertSame('+32412345678', $message->to_phone);
        $this->assertSame(SmsMessage::STATUS_SENT, $message->status);
        $this->assertStringStartsWith('mock_sms_', $message->external_id);
        $this->assertSame($user->id, $message->user_id);
    }

    public function test_dispatch_rejects_invalid_e164_phone(): void
    {
        $message = app(SmsService::class)->dispatch(
            toPhone: '0412345678',
            body: 'No country code',
        );

        $this->assertNull($message);
        $this->assertSame(0, SmsMessage::count());
    }

    public function test_dispatch_normalizes_phone_with_double_zero_prefix(): void
    {
        $message = app(SmsService::class)->dispatch(
            toPhone: '0032 412 34 56 78',
            body: 'normalize me',
        );

        $this->assertNotNull($message);
        $this->assertSame('+32412345678', $message->to_phone);
    }

    public function test_dispatch_is_idempotent_with_same_key(): void
    {
        $a = app(SmsService::class)->dispatch(
            toPhone: '+32412345678',
            body: 'msg 1',
            idempotencyKey: 'test:idempotent:001',
        );

        $b = app(SmsService::class)->dispatch(
            toPhone: '+32412345678',
            body: 'msg 2 (should be ignored)',
            idempotencyKey: 'test:idempotent:001',
        );

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, SmsMessage::count());
    }

    public function test_mock_provider_returns_failed_when_phone_contains_fail_keyword(): void
    {
        // E.164 validation in SmsService blocks letters; the mock provider's
        // "fail" trigger is only reachable when calling the provider directly.
        $provider = new SmsMockProvider();

        $result = $provider->send(new \App\Services\Sms\SmsSendRequest(
            toPhone: 'failtest',
            body: 'auto fail',
        ));

        $this->assertFalse($result->accepted);
        $this->assertSame('mock_fail', $result->failureCode);
    }

    public function test_rate_limit_per_phone_per_hour_creates_rate_limited_record(): void
    {
        Config::set('sms.rate_limits.per_phone_per_hour', 2);

        $svc = app(SmsService::class);
        $svc->dispatch(toPhone: '+32412345678', body: '1');
        $svc->dispatch(toPhone: '+32412345678', body: '2');

        $third = $svc->dispatch(toPhone: '+32412345678', body: '3');

        $this->assertNotNull($third);
        $this->assertSame(SmsMessage::STATUS_RATE_LIMITED, $third->status);
        $this->assertSame(3, SmsMessage::count());
    }

    public function test_apply_status_update_marks_message_delivered(): void
    {
        $message = app(SmsService::class)->dispatch(
            toPhone: '+32412345678',
            body: 'will be delivered',
        );

        $this->assertNotNull($message->external_id);

        $result = app(SmsService::class)->applyStatusUpdate(new SmsStatusUpdate(
            externalId: $message->external_id,
            status: SmsMessage::STATUS_DELIVERED,
            raw: ['ok' => true],
        ));

        $this->assertNotNull($result);
        $this->assertSame(SmsMessage::STATUS_DELIVERED, $result->status);
        $this->assertNotNull($result->delivered_at);
    }

    public function test_apply_status_update_returns_null_for_unknown_external_id(): void
    {
        $result = app(SmsService::class)->applyStatusUpdate(new SmsStatusUpdate(
            externalId: 'nonexistent_id_42',
            status: SmsMessage::STATUS_DELIVERED,
        ));

        $this->assertNull($result);
    }

    public function test_legacy_send_returns_null_when_sms_disabled(): void
    {
        Config::set('sms.enabled', false);

        $result = app(SmsService::class)->send('+32412345678', 'disabled');

        $this->assertNull($result);
        $this->assertSame(0, SmsMessage::count());
    }

    public function test_legacy_send_returns_null_for_empty_phone(): void
    {
        $result = app(SmsService::class)->send(null, 'no phone');

        $this->assertNull($result);
        $this->assertSame(0, SmsMessage::count());
    }
}

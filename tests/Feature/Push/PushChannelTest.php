<?php

namespace Tests\Feature\Push;

use App\Models\DeviceToken;
use App\Models\PushNotification;
use App\Models\User;
use App\Notifications\Channels\PushChannel;
use App\Services\Push\Providers\PushMockProvider;
use App\Services\Push\PushProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Tests\TestCase;

class PushChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(PushProviderInterface::class, PushMockProvider::class);
    }

    public function test_channel_sends_via_push_service_using_to_push_payload(): void
    {
        $user = User::factory()->client()->create();

        $raw = 'tok_chan_001';
        DeviceToken::create([
            'user_id' => $user->id,
            'platform' => 'ios',
            'provider' => 'mock',
            'token' => $raw,
            'token_hash' => DeviceToken::hashToken($raw),
            'preferences' => ['transactional' => true],
            'last_used_at' => now(),
        ]);

        $notification = new TestPushNotification();

        $channel = app(PushChannel::class);
        $results = $channel->send($user, $notification);

        $this->assertNotEmpty($results);
        $this->assertInstanceOf(PushNotification::class, $results[0]);
        $this->assertSame('Booking confirmé', $results[0]->title);
        $this->assertSame(PushNotification::STATUS_SENT, $results[0]->status);
        $this->assertSame(['booking_id' => 42], $results[0]->data);
    }

    public function test_channel_returns_empty_when_notifiable_is_not_user(): void
    {
        $notification = new TestPushNotification();

        $channel = app(PushChannel::class);
        $result = $channel->send(new \stdClass(), $notification);

        $this->assertSame([], $result);
    }

    public function test_channel_returns_empty_when_no_to_push_method(): void
    {
        $user = User::factory()->client()->create();
        $notification = new class extends Notification {
            // No toPush method
        };

        $channel = app(PushChannel::class);
        $result = $channel->send($user, $notification);

        $this->assertSame([], $result);
    }
}

class TestPushNotification extends Notification
{
    public function via($notifiable): array
    {
        return [PushChannel::class];
    }

    public function toPush($notifiable): array
    {
        return [
            'title' => 'Booking confirmé',
            'body' => 'Votre RDV est confirmé pour demain 10h.',
            'data' => ['booking_id' => 42],
            'category' => PushNotification::CATEGORY_TRANSACTIONAL,
        ];
    }

    public function pushIdempotencyKey($notifiable): ?string
    {
        return 'booking-confirmed:42:' . $notifiable->id;
    }
}

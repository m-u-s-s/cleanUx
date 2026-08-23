<?php

namespace App\Notifications\Channels;

use App\Models\PushNotification;
use App\Models\User;
use App\Services\Push\PushService;
use Illuminate\Notifications\Notification;

/** Push notification channel — extension Laravel. */
class PushChannel
{
    public function __construct(protected PushService $service) {}

    public function send(mixed $notifiable, Notification $notification): array
    {
        if (! method_exists($notification, 'toPush')) {
            return [];
        }

        // Notifiable must be a User to look up device tokens
        if (! $notifiable instanceof User) {
            return [];
        }

        $payload = $notification->toPush($notifiable);
        if (! is_array($payload) || empty($payload['body'])) {
            return [];
        }

        $idempotencyKey = method_exists($notification, 'pushIdempotencyKey')
            ? $notification->pushIdempotencyKey($notifiable)
            : null;

        $category = $payload['category'] ?? PushNotification::CATEGORY_TRANSACTIONAL;

        return $this->service->dispatchToUser(
            user: $notifiable,
            title: $payload['title'] ?? null,
            body: $payload['body'],
            data: $payload['data'] ?? [],
            category: $category,
            idempotencyKey: $idempotencyKey,
        );
    }
}

<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Services\Push\WebPushSender;
use Illuminate\Notifications\Notification;

/** Phase 8 — Channel Laravel pour envoyer des notifications via Web Push. */
class WebPushChannel
{
    public function __construct(
        protected WebPushSender $sender,
    ) {}

    public function send($notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWebPush')) {
            return;
        }

        $payload = $notification->toWebPush($notifiable);
        if (empty($payload)) {
            return;
        }

        if (! method_exists($notifiable, 'getKey')) {
            return;
        }

        $userId = $notifiable->getKey();

        $user = $notifiable instanceof User
            ? $notifiable
            : User::find($userId);

        if ($user) {
            $this->sender->sendToUser($user, $payload);
        }
    }
}

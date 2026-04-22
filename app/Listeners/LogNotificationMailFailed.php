<?php

namespace App\Listeners;

use App\Services\Email\EmailLogService;
use Illuminate\Notifications\Events\NotificationFailed;

class LogNotificationMailFailed
{
    public function __construct(protected EmailLogService $emailLogService) {}

    public function handle(NotificationFailed $event): void
    {
        if ($event->channel !== 'mail') {
            return;
        }

        $this->emailLogService->logNotification('failed', $event->notifiable, $event->notification, $event->channel, [
            'data' => $event->data,
        ]);
    }
}

<?php

namespace App\Listeners;

use App\Services\Email\EmailLogService;
use Illuminate\Notifications\Events\NotificationSent;

class LogNotificationMailSent
{
    public function __construct(protected EmailLogService $emailLogService) {}

    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'mail') {
            return;
        }

        $this->emailLogService->logNotification('sent', $event->notifiable, $event->notification, $event->channel, [
            'response' => is_scalar($event->response) ? (string) $event->response : null,
        ]);
    }
}

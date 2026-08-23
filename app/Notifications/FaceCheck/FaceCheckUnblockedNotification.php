<?php

namespace App\Notifications\FaceCheck;

use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** « VOUS POUVEZ RETRAVAILLER » — et un contrôle vous sera demandé tout de suite. */
class FaceCheckUnblockedNotification extends Notification
{
    use InteractsWithUserNotificationPreferences;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable, 'face_check_unblocked', ['database', 'mail']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('face_check.notifications.unblocked.subject'))
            ->greeting(__('face_check.notifications.unblocked.greeting', ['name' => $notifiable->name ?? '']))
            ->line(__('face_check.notifications.unblocked.line_lifted'))
            ->line(__('face_check.notifications.unblocked.line_next'))
            ->action(__('face_check.notifications.unblocked.action'), url('/provider/verification-faciale'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->basePayload([
            'type' => 'face_check_unblocked',
            'message' => __('face_check.notifications.unblocked.line_lifted'),
            'url' => '/provider/verification-faciale',
        ]);
    }
}

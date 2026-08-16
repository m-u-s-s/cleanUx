<?php

namespace App\Notifications\FaceCheck;

use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * « VOUS POUVEZ RETRAVAILLER » — et un contrôle vous sera demandé tout de suite.
 *
 * Sans ce message, un prestataire débloqué le découvrait en réessayant, parfois des jours plus
 * tard. Une décision d'administrateur qui change la journée de quelqu'un doit lui parvenir ; le
 * silence transforme une bonne nouvelle en temps perdu.
 *
 * La seconde ligne compte autant que la première : la levée rend la POSSIBILITÉ de prouver son
 * identité, elle n'en dispense pas. La laisser deviner produirait un second blocage vécu comme
 * une injustice.
 */
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

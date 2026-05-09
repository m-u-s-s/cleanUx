<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FeedbackInviteNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Booking $rendezVous,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Comment s’est passée votre intervention ?')
            ->greeting('Bonjour '.$notifiable->name)
            ->line('Votre mission est terminée. Votre avis nous aide à améliorer la qualité du service.')
            ->action('Laisser un feedback', route('feedback.create', $this->rendezVous))
            ->line('Merci pour votre retour.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'feedback_invite',
            'title' => 'Votre avis compte',
            'message' => 'La mission est terminée. Vous pouvez laisser une note et un commentaire.',
            'rendez_vous_id' => $this->rendezVous->id,
            'url' => route('feedback.create', $this->rendezVous),
        ];
    }
}
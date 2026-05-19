<?php

namespace App\Notifications\Rating;

use App\Models\Booking;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RatingRequestedNotification extends Notification
{
    use Queueable;
    use InteractsWithUserNotificationPreferences;

    public function __construct(public Booking $booking, public string $direction)
    {
    }

    public function via($notifiable): array
    {
        return $this->preferredChannels($notifiable, 'rating_requested', ['mail', 'database']);
    }

    public function toMail($notifiable): MailMessage
    {
        $isClient = $this->direction === \App\Models\Feedback::DIRECTION_CLIENT_TO_PROVIDER;

        return (new MailMessage)
            ->subject('CleanUx · Notez votre dernière prestation')
            ->line($isClient
                ? 'Votre prestation est terminée. Aidez la communauté en notant votre prestataire.'
                : 'La mission est terminée. Notez votre expérience client.')
            ->line('Référence : ' . $this->booking->booking_reference)
            ->action('Laisser un avis', url('/dashboard'))
            ->line('Vous avez 14 jours pour laisser votre avis.');
    }

    public function toArray($notifiable): array
    {
        $isClient = $this->direction === \App\Models\Feedback::DIRECTION_CLIENT_TO_PROVIDER;

        return $this->basePayload([
            'type' => 'rating_requested',
            'severity' => 'info',
            'title' => $isClient ? 'Notez votre prestation' : 'Notez votre client',
            'message' => 'Référence ' . $this->booking->booking_reference,
            'booking_id' => $this->booking->id,
            'direction' => $this->direction,
        ]);
    }
}

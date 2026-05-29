<?php

namespace App\Notifications\Payments;

use App\Models\Booking;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification
{
    use InteractsWithUserNotificationPreferences;
    use Queueable;

    public function __construct(
        public readonly Booking $booking,
        public readonly ?string $failureMessage = null,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable, 'payment_failed', ['mail', 'database']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('CleanUx · Echec du paiement pour votre reservation #'.$this->booking->booking_reference)
            ->greeting('Bonjour,')
            ->line('Le paiement de votre reservation n\'a pas pu etre traite.')
            ->when($this->failureMessage, fn ($mail) => $mail->line('Motif : '.$this->failureMessage))
            ->line('Veuillez mettre a jour votre moyen de paiement et reessayer.')
            ->action('Mettre a jour mon paiement', url('/dashboard/client/reservations/'.$this->booking->id))
            ->line('Si vous avez besoin d\'aide, contactez notre support.');
    }

    public function toArray(object $notifiable): array
    {
        return $this->basePayload([
            'type' => 'payment_failed',
            'severity' => 'error',
            'title' => 'Echec du paiement',
            'message' => 'Le paiement de votre reservation #'.$this->booking->booking_reference.' a echoue.',
            'booking_id' => $this->booking->id,
            'reference' => $this->booking->booking_reference,
            'failure_message' => $this->failureMessage,
            'action_url' => '/dashboard/client/reservations/'.$this->booking->id,
        ]);
    }
}

<?php

namespace App\Notifications\Rating;

use App\Models\Feedback;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RatingReceivedNotification extends Notification
{
    use Queueable;
    use InteractsWithUserNotificationPreferences;

    public function __construct(public Feedback $feedback)
    {
    }

    public function via($notifiable): array
    {
        return $this->preferredChannels($notifiable, 'rating_received', ['mail', 'database']);
    }

    public function toMail($notifiable): MailMessage
    {
        $stars = str_repeat('★', (int) $this->feedback->effectiveRating())
              . str_repeat('☆', max(0, 5 - (int) $this->feedback->effectiveRating()));

        $subject = $this->feedback->isClientToProvider()
            ? 'CleanUx · Vous avez reçu un nouvel avis'
            : 'CleanUx · Un prestataire vous a noté';

        $mail = (new MailMessage)
            ->subject($subject)
            ->line('Note reçue : '.$stars.' ('.$this->feedback->effectiveRating().'/5)');

        if ($this->feedback->effectiveComment()) {
            $mail->line('Commentaire : "'.$this->feedback->effectiveComment().'"');
        }

        return $mail->action('Voir mes avis', url('/dashboard'));
    }

    public function toArray($notifiable): array
    {
        return $this->basePayload([
            'type' => 'rating_received',
            'severity' => 'info',
            'title' => $this->feedback->isClientToProvider()
                ? 'Nouvel avis reçu'
                : 'Vous avez été noté',
            'message' => sprintf(
                'Note : %d/5%s',
                (int) $this->feedback->effectiveRating(),
                $this->feedback->effectiveComment() ? ' — "'.$this->feedback->effectiveComment().'"' : ''
            ),
            'feedback_id' => $this->feedback->id,
            'rating' => (int) $this->feedback->effectiveRating(),
            'direction' => $this->feedback->direction,
            'booking_id' => $this->feedback->booking_id ?? $this->feedback->rendez_vous_id,
        ]);
    }
}

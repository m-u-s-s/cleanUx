<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** L'ANNONCE QUE PERSONNE NE FAISAIT. */
class MissionEnRetardNotification extends Notification
{
    use Queueable;

    /**
     * @param  array{arrivee_at: string|null, motif: string|null}|null  $annonce
     */
    public function __construct(
        public Booking $booking,
        public int $minutes,
        public ?array $annonce = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Votre prestataire a du retard')
            ->greeting('Bonjour,')
            ->line('Votre intervention '.$this->booking->booking_reference.' devait commencer il y a '.$this->minutes.' minutes.');

        if (($this->annonce['motif'] ?? null) !== null) {
            $message->line('Le prestataire indique : '.$this->annonce['motif']);
        }

        return $message
            ->line('Vous pouvez attendre, décaler l’intervention, ou l’annuler sans frais.')
            ->action('Gérer ma mission', url('/client/dashboard'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mission_en_retard',
            'booking_id' => $this->booking->id,
            'booking_reference' => $this->booking->booking_reference,
            'minutes' => $this->minutes,
            'arrivee_annoncee_at' => $this->annonce['arrivee_at'] ?? null,
            'motif' => $this->annonce['motif'] ?? null,
        ];
    }
}

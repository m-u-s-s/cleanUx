<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * L'ANNONCE QUE PERSONNE NE FAISAIT.
 *
 * Le client attendait, regardait une carte immobile, et apprenait le retard en le vivant. Cette
 * notification part une seule fois par réservation — le tampon est posé par `MissionDelayService`,
 * pas ici : une notification qui déciderait elle-même de son unicité serait contournée dès qu'un
 * autre chemin l'enverrait.
 *
 * Elle dit le retard ET ce qui est ouvert. Annoncer un problème sans dire ce qu'on peut en faire
 * transforme l'information en inquiétude.
 */
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

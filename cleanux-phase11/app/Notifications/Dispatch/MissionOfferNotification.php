<?php

namespace App\Notifications\Dispatch;

use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Phase 11 — Notification poussée au prestataire pour une nouvelle mission.
 *
 * Inspiré du dispatch Uber : push notification avec actions "Accepter / Refuser"
 * pour une réponse en 1 tap. Si l'app est fermée, la push réveille le téléphone.
 *
 * Canaux :
 *   - database (pour l'historique notifications)
 *   - mail (fallback / archive — peut être retiré si volume trop élevé)
 *   - WebPushChannel (Phase 8 — pour la push browser/PWA)
 */
class MissionOfferNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Mission $mission,
        public MissionAssignment $assignment,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (class_exists(WebPushChannel::class)) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reference = $this->mission->booking?->booking_reference ?? "M-{$this->mission->id}";

        return (new MailMessage)
            ->subject("Nouvelle mission proposée : {$reference}")
            ->greeting('Bonjour,')
            ->line("Une nouvelle mission vous est proposée : {$reference}.")
            ->line('Réponse attendue dans 15 secondes.')
            ->action('Voir la mission', url("/provider/missions/{$this->assignment->id}/offer"))
            ->line('Si vous ne répondez pas à temps, la mission sera proposée à un autre prestataire.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'              => 'mission.offer',
            'mission_id'        => $this->mission->id,
            'assignment_id'     => $this->assignment->id,
            'booking_reference' => $this->mission->booking?->booking_reference,
            'expires_at'        => $this->assignment->expires_at?->toIso8601String(),
            'planned_start_at'  => $this->mission->planned_start_at?->toIso8601String(),
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        $booking = $this->mission->booking;
        $reference = $booking?->booking_reference ?? "M-{$this->mission->id}";

        $serviceName = $booking?->serviceCatalog?->name ?? 'Mission';
        $address = $booking?->city ?? '';

        $body = $serviceName;
        if ($address) {
            $body .= " — {$address}";
        }
        $body .= ' (15s pour répondre)';

        return [
            'title' => '🚨 Nouvelle mission',
            'body'  => $body,
            'url'   => "/provider/missions/{$this->assignment->id}/offer",
            'tag'   => 'mission-offer-' . $this->assignment->id,
            'requireInteraction' => true,
            'data' => [
                'mission_id'    => $this->mission->id,
                'assignment_id' => $this->assignment->id,
                'expires_at'    => $this->assignment->expires_at?->toIso8601String(),
                'reference'     => $reference,
            ],
            'actions' => [
                ['action' => 'accept',  'title' => '✓ Accepter'],
                ['action' => 'decline', 'title' => '✕ Refuser'],
            ],
        ];
    }
}

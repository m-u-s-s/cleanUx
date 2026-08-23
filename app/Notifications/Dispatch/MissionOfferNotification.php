<?php

namespace App\Notifications\Dispatch;

use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Phase 11 — Notification poussée au prestataire pour une nouvelle mission. */
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
            // `route()` PLUTÔT QU'UNE URL ÉCRITE EN DUR (2026-08-05).
            ->action('Voir la mission', route('provider.missions.offer', $this->assignment))
            ->line('Si vous ne répondez pas à temps, la mission sera proposée à un autre prestataire.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mission.offer',
            'mission_id' => $this->mission->id,
            'assignment_id' => $this->assignment->id,
            'booking_reference' => $this->mission->booking?->booking_reference,
            'expires_at' => $this->assignment->expires_at?->toIso8601String(),
            'planned_start_at' => $this->mission->planned_start_at?->toIso8601String(),
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
            'body' => $body,
            // Chemin RELATIF, comme l'attend le client push — mais dérivé de la route, plus figé
            // en dur : le troisième argument de `route()` demande une URL relative.
            'url' => route('provider.missions.offer', $this->assignment, false),
            'tag' => 'mission-offer-'.$this->assignment->id,
            'requireInteraction' => true,
            'data' => [
                'mission_id' => $this->mission->id,
                'assignment_id' => $this->assignment->id,
                'expires_at' => $this->assignment->expires_at?->toIso8601String(),
                'reference' => $reference,
            ],
            'actions' => [
                ['action' => 'accept',  'title' => '✓ Accepter'],
                ['action' => 'decline', 'title' => '✕ Refuser'],
            ],
        ];
    }
}

<?php

namespace App\Notifications;

use App\Models\Mission;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmployeArriveNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Mission $mission,
        public string $startCode
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'mail', WebPushChannel::class];
    }

    public function toWebPush($notifiable): array
    {
        return [
            'title' => 'Votre employé est arrivé',
            'body'  => "Mission {$this->mission->rendezVous?->booking_reference}",
            'url'   => '/dashboard/client/rendezvous',
            'tag'   => 'mission-arrived-' . $this->mission->id,
            'requireInteraction' => true,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre employé est arrivé')
            ->greeting('Bonjour,')
            ->line('Votre employé est arrivé pour la mission ' . $this->mission->rendezVous?->booking_reference . '.')
            ->line('Code de début de mission : ' . $this->startCode)
            ->line('Donnez ce code à l’employé pour démarrer la mission.')
            ->action('Voir le suivi', url('/client/dashboard'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'employee_arrived',
            'mission_id' => $this->mission->id,
            'rendez_vous_id' => $this->mission->rendez_vous_id,
            'booking_reference' => $this->mission->rendezVous?->booking_reference,
            'service_identifier' => $this->mission->rendezVous?->service_identifier_display,
            'service_label' => $this->mission->rendezVous?->service_display_name,
            'employee_name' => $this->mission->leadEmployee?->name,
            'status' => $this->mission->status,
            'start_code' => $this->startCode,
        ];
    }
}

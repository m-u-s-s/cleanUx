<?php

namespace App\Notifications;

use App\Models\Mission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmployeEnRouteNotification extends Notification
{
    use Queueable;

    public function __construct(public Mission $mission)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre employé est en route')
            ->greeting('Bonjour,')
            ->line('Votre employé est en route pour la mission '.$this->mission->rendezVous?->booking_reference.'.')
            ->line('Vous pouvez suivre son arrivée depuis votre espace client.')
            ->action('Voir le suivi', url('/client/dashboard'))
            ->line('Merci de votre confiance.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'employee_en_route',
            'mission_id' => $this->mission->id,
            'rendez_vous_id' => $this->mission->rendez_vous_id,
            'booking_reference' => $this->mission->rendezVous?->booking_reference,
            'service_identifier' => $this->mission->rendezVous?->service_identifier_display,
            'service_label' => $this->mission->rendezVous?->service_display_name,
            'employee_name' => $this->mission->leadEmployee?->name,
            'status' => $this->mission->status,
        ];
    }
}
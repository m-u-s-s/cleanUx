<?php

namespace App\Notifications;

use App\Models\Mission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MissionCompletedNotification extends Notification
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
            ->subject('Votre mission est terminée')
            ->greeting('Bonjour,')
            ->line('La mission '.$this->mission->rendezVous?->booking_reference.' est terminée.')
            ->line('Vous pouvez maintenant valider la présence ou signaler un problème.')
            ->action('Voir la mission', url('/client/dashboard'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mission_completed',
            'mission_id' => $this->mission->id,
            'rendez_vous_id' => $this->mission->rendez_vous_id,
            'booking_reference' => $this->mission->rendezVous?->booking_reference,
            'employee_name' => $this->mission->leadEmployee?->name,
            'status' => $this->mission->status,
        ];
    }
}
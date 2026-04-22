<?php

namespace App\Notifications;

use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RdvConfirmeNotification extends Notification
{
    use Queueable;
    use InteractsWithUserNotificationPreferences;

    public function __construct(public $rdv)
    {
    }

    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable, 'booking_confirmed', ['mail', 'database']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('CleanUx · Rendez-vous confirmé')
            ->line('Votre rendez-vous a bien été confirmé.')
            ->line('Date : ' . $this->rdv->date . ' à ' . $this->rdv->heure)
            ->action('Voir mon dashboard', url('/dashboard/client'))
            ->line('Merci pour votre confiance.');
    }

    public function toArray(object $notifiable): array
    {
        return $this->basePayload([
            'type' => 'rendezvous',
            'severity' => 'success',
            'title' => 'Rendez-vous confirmé',
            'message' => 'Votre rendez-vous du ' . $this->rdv->date . ' à ' . $this->rdv->heure . ' a été confirmé.',
            'rdv_id' => $this->rdv->id,
            'status' => 'confirme',
            'zone_name' => $this->rdv->serviceZone?->name,
            'action_url' => url('/dashboard/client'),
        ]);
    }
}

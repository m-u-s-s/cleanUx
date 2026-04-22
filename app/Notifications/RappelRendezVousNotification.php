<?php

namespace App\Notifications;

use App\Models\RendezVous;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RappelRendezVousNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use InteractsWithUserNotificationPreferences;

    public function __construct(
        public RendezVous $rdv,
        public string $timing = '24h'
    ) {
    }

    public function via($notifiable): array
    {
        $eventKey = $this->timing === '2h' ? 'booking_reminder_2h' : 'booking_reminder_24h';

        return $this->preferredChannels($notifiable, $eventKey, ['mail', 'database']);
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('CleanUx · Rappel de votre intervention')
            ->line("Petit rappel : votre {$this->rdv->service_display_name} est prévu dans {$this->timing}.")
            ->line('Date : ' . $this->rdv->date . ' à ' . $this->rdv->heure)
            ->line('Adresse : ' . $this->rdv->location_display)
            ->action('Voir mon espace client', url('/dashboard/client'));
    }

    public function toArray($notifiable): array
    {
        return $this->basePayload([
            'type' => 'rendezvous',
            'severity' => $this->timing === '2h' ? 'warning' : 'info',
            'title' => 'Rappel de rendez-vous',
            'message' => "Rappel : votre {$this->rdv->service_display_name} est prévue dans {$this->timing}.",
            'rdv_id' => $this->rdv->id,
            'timing' => $this->timing,
            'date' => $this->rdv->date,
            'heure' => $this->rdv->heure,
            'service_identifier' => $this->rdv->service_identifier_display,
            'service_label' => $this->rdv->service_display_name,
            'zone_name' => $this->rdv->serviceZone?->name,
            'location_display' => $this->rdv->location_display,
            'action_url' => url('/dashboard/client'),
        ]);
    }
}

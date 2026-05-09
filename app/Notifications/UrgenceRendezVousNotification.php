<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UrgenceRendezVousNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use InteractsWithUserNotificationPreferences;

    public function __construct(public Booking $rdv)
    {
    }

    public function via($notifiable): array
    {
        return $this->preferredChannels($notifiable, 'urgent_booking', ['mail', 'database']);
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('CleanUx · Intervention urgente en attente')
            ->line('Une demande urgente est toujours en attente de traitement.')
            ->line('Client : ' . ($this->rdv->client->name ?? '—'))
            ->line('Service : ' . $this->rdv->service_display_name)
            ->line('Lieu : ' . $this->rdv->location_display)
            ->line('Date : ' . $this->rdv->date . ' à ' . $this->rdv->heure)
            ->action('Voir le tableau de bord admin', url('/admin/dashboard'));
    }

    public function toArray($notifiable): array
    {
        return $this->basePayload([
            'type' => 'urgent',
            'severity' => 'danger',
            'title' => 'Urgence en attente',
            'message' => '🚨 Une demande urgente est toujours en attente pour ' . $this->rdv->service_display_name . '.',
            'rdv_id' => $this->rdv->id,
            'service_identifier' => $this->rdv->service_identifier_display,
            'service_label' => $this->rdv->service_display_name,
            'priorite' => $this->rdv->priorite,
            'status' => $this->rdv->status,
            'zone_name' => $this->rdv->serviceZone?->name,
            'location_display' => $this->rdv->location_display,
            'action_url' => url('/admin/dashboard'),
        ]);
    }
}

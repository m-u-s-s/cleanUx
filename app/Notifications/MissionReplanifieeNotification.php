<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MissionReplanifieeNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use InteractsWithUserNotificationPreferences;

    public function __construct(
        public Booking $rdv,
        public string $ancienEmploye,
        public string $ancienneDate,
        public string $ancienneHeure
    ) {
    }

    public function via($notifiable): array
    {
        return $this->preferredChannels($notifiable, 'booking_status', ['mail', 'database']);
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('CleanUx · Votre intervention a été replanifiée')
            ->line("Votre demande de {$this->rdv->service_display_name} a été replanifiée par notre équipe.")
            ->line('Ancien créneau : ' . $this->ancienneDate . ' à ' . $this->ancienneHeure)
            ->line('Nouveau créneau : ' . $this->rdv->date . ' à ' . $this->rdv->heure)
            ->line('Employé assigné : ' . ($this->rdv->employe->name ?? '—'))
            ->line('Lieu : ' . $this->rdv->location_display)
            ->action('Voir mon espace client', url('/dashboard/client'));
    }

    public function toArray($notifiable): array
    {
        return $this->basePayload([
            'type' => 'rendezvous',
            'severity' => 'warning',
            'title' => 'Mission replanifiée',
            'message' => 'Votre intervention de ' . $this->rdv->service_display_name . ' a été replanifiée.',
            'rdv_id' => $this->rdv->id,
            'service_identifier' => $this->rdv->service_identifier_display,
            'service_label' => $this->rdv->service_display_name,
            'ancienne_date' => $this->ancienneDate,
            'ancienne_heure' => $this->ancienneHeure,
            'nouvelle_date' => $this->rdv->date,
            'nouvelle_heure' => $this->rdv->heure,
            'employe' => $this->rdv->employe->name ?? null,
            'status' => $this->rdv->status,
            'zone_name' => $this->rdv->serviceZone?->name,
            'location_display' => $this->rdv->location_display,
            'action_url' => url('/dashboard/client'),
        ]);
    }
}

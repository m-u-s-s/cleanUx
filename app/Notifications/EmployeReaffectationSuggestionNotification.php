<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\User;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmployeReaffectationSuggestionNotification extends Notification
{
    use Queueable;
    use InteractsWithUserNotificationPreferences;

    public string $employeSurcharge;
    public string $employeSuggere;

    public function __construct(
        public Booking $rdv,
        User|string|null $employeSurcharge,
        User|string|null $employeSuggere
    ) {
        $this->employeSurcharge = $employeSurcharge instanceof User ? $employeSurcharge->name : (string) ($employeSurcharge ?: '—');
        $this->employeSuggere = $employeSuggere instanceof User ? $employeSuggere->name : (string) ($employeSuggere ?: '—');
    }

    public function via($notifiable): array
    {
        return $this->preferredChannels($notifiable, 'reassignment_suggestion', ['mail', 'database']);
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('CleanUx · Suggestion automatique de réaffectation')
            ->line('Une mission pourrait être réaffectée pour équilibrer la charge.')
            ->line('Mission #' . $this->rdv->id)
            ->line('Employé surchargé : ' . $this->employeSurcharge)
            ->line('Employé suggéré : ' . $this->employeSuggere)
            ->action('Voir le dashboard admin', url('/admin/dashboard'));
    }

    public function toArray($notifiable): array
    {
        return $this->basePayload([
            'type' => 'admin',
            'severity' => 'warning',
            'title' => 'Suggestion de réaffectation',
            'message' => 'Suggestion automatique de réaffectation pour la mission #' . $this->rdv->id,
            'rdv_id' => $this->rdv->id,
            'employe_surcharge' => $this->employeSurcharge,
            'employe_suggere' => $this->employeSuggere,
            'zone_name' => $this->rdv->serviceZone?->name,
            'action_url' => url('/admin/dashboard'),
        ]);
    }
}

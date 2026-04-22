<?php

namespace App\Notifications;

use App\Models\RendezVous;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DemandeFeedbackNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use InteractsWithUserNotificationPreferences;

    public function __construct(public RendezVous $rdv)
    {
    }

    public function via($notifiable): array
    {
        return $this->preferredChannels($notifiable, 'feedback_request', ['mail', 'database']);
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('CleanUx · Comment s’est passée votre intervention ?')
            ->line("Votre {$this->rdv->service_display_name} a bien eu lieu ?")
            ->line('Votre avis nous aide à améliorer la qualité de nos prestations.')
            ->action('Laisser un feedback', url('/feedback/ajouter/' . $this->rdv->id))
            ->line('Merci pour votre confiance.');
    }

    public function toArray($notifiable): array
    {
        return $this->basePayload([
            'type' => 'feedback',
            'severity' => 'info',
            'title' => 'Feedback demandé',
            'message' => 'Merci de laisser votre avis sur votre intervention récente de ' . $this->rdv->service_display_name . '.',
            'rdv_id' => $this->rdv->id,
            'service_identifier' => $this->rdv->service_identifier_display,
            'service_label' => $this->rdv->service_display_name,
            'location_display' => $this->rdv->location_display,
            'action_url' => url('/feedback/ajouter/' . $this->rdv->id),
        ]);
    }
}

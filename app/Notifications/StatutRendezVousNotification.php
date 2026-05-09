<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Support\Domain\BookingStatus;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StatutRendezVousNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use InteractsWithUserNotificationPreferences;

    public function __construct(public Booking $rdv)
    {
    }

    public function via($notifiable)
    {
        return $this->preferredChannels($notifiable, 'booking_status', ['mail', 'database']);
    }

    public function toMail($notifiable)
    {
        $statusText = BookingStatus::mailLabel((string) $this->rdv->status);

        $mail = (new MailMessage)
            ->subject('CleanUx · Mise à jour de votre demande')
            ->line("Votre demande de {$this->rdv->service_display_name} a été {$statusText}.")
            ->line('Date : ' . $this->rdv->date . ' à ' . $this->rdv->heure)
            ->line('Lieu : ' . $this->rdv->location_display);

        if ($this->rdv->status === BookingStatus::EN_ROUTE) {
            $mail->line('Notre employé est en route vers votre adresse.');
        }

        if ($this->rdv->status === BookingStatus::SUR_PLACE) {
            $mail->line('L’intervention a commencé sur place.');
        }

        if ($this->rdv->status === BookingStatus::TERMINE) {
            $mail->line('L’intervention est terminée. Merci pour votre confiance.');
        }

        return $mail->action('Voir mon espace client', url('/dashboard/client'));
    }

    public function toArray($notifiable)
    {
        $statusText = BookingStatus::label((string) $this->rdv->status);

        return $this->basePayload([
            'type' => $this->rdv->status === BookingStatus::REFUSE ? 'urgent' : 'rendezvous',
            'severity' => BookingStatus::notificationSeverity((string) $this->rdv->status),
            'title' => 'Mise à jour de rendez-vous',
            'message' => 'Votre demande de ' . $this->rdv->service_display_name . ' a été ' . $statusText . '.',
            'rdv_id' => $this->rdv->id,
            'service_identifier' => $this->rdv->service_identifier_display,
            'service_label' => $this->rdv->service_display_name,
            'date' => $this->rdv->date,
            'heure' => $this->rdv->heure,
            'status' => $this->rdv->status,
            'adresse' => $this->rdv->adresse,
            'ville' => $this->rdv->ville,
            'location_display' => $this->rdv->location_display,
            'zone_name' => $this->rdv->serviceZone?->name,
            'action_url' => url('/dashboard/client'),
        ]);
    }
}

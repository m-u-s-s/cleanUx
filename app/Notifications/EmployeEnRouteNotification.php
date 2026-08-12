<?php

namespace App\Notifications;

use App\Models\Mission;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmployeEnRouteNotification extends Notification
{
    use Queueable;

    public function __construct(public Mission $mission) {}

    use InteractsWithUserNotificationPreferences;

    public function via(object $notifiable): array
    {
        // « Le prestataire arrive » est l'information la plus périssable du parcours : elle vaut
        // pendant vingt minutes. Le courriel la livre trop tard pour être utile.
        return $this->preferredChannels($notifiable, 'employe_en_route', ['database', 'mail', 'sms']);
    }

    public function toSms(object $notifiable): string
    {
        return sprintf(
            'Brio : votre prestataire est en route pour l intervention %s.',
            $this->mission->booking->booking_reference ?? '',
        );
    }

    public function smsIdempotencyKey(object $notifiable): string
    {
        return 'mission:enroute:'.$this->mission->id;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre employé est en route')
            ->greeting('Bonjour,')
            ->line('Votre employé est en route pour la mission '.$this->mission->booking?->booking_reference.'.')
            ->line('Vous pouvez suivre son arrivée depuis votre espace client.')
            ->action('Voir le suivi', url('/client/dashboard'))
            ->line('Merci de votre confiance.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'employee_en_route',
            'mission_id' => $this->mission->id,
            'rendez_vous_id' => $this->mission->booking_id,
            'booking_reference' => $this->mission->booking?->booking_reference,
            'service_identifier' => $this->mission->booking?->service_identifier_display,
            'service_label' => $this->mission->booking?->service_display_name,
            'employee_name' => $this->mission->leadEmployee?->name,
            'status' => $this->mission->status,
        ];
    }
}

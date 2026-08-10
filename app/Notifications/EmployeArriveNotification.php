<?php

namespace App\Notifications;

use App\Models\Mission;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmployeArriveNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Mission $mission,
        public string $startCode
    ) {}

    use \App\Support\Notifications\InteractsWithUserNotificationPreferences;

    public function via($notifiable): array
    {
        // Le client doit ouvrir sa porte : s'il ne consulte ni l'application ni ses courriels à cet
        // instant, l'intervention s'arrête sur le palier.
        return $this->preferredChannels(
            $notifiable,
            'employe_arrive',
            ['database', 'mail', 'sms', WebPushChannel::class],
        );
    }

    public function toSms(object $notifiable): string
    {
        return sprintf(
            'Brio : votre prestataire est arrive pour l intervention %s.',
            $this->mission->booking->booking_reference ?? '',
        );
    }

    public function smsIdempotencyKey(object $notifiable): string
    {
        return 'mission:arrived:'.$this->mission->id;
    }

    public function toWebPush($notifiable): array
    {
        return [
            'title' => 'Votre employé est arrivé',
            'body' => "Mission {$this->mission->booking?->booking_reference}",
            'url' => '/dashboard/client/rendezvous',
            'tag' => 'mission-arrived-'.$this->mission->id,
            'requireInteraction' => true,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre employé est arrivé')
            ->greeting('Bonjour,')
            ->line('Votre employé est arrivé pour la mission '.$this->mission->booking?->booking_reference.'.')
            ->line('Code de début de mission : '.$this->startCode)
            ->line('Donnez ce code à l’employé pour démarrer la mission.')
            ->action('Voir le suivi', url('/client/dashboard'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'employee_arrived',
            'mission_id' => $this->mission->id,
            'rendez_vous_id' => $this->mission->booking_id,
            'booking_reference' => $this->mission->booking?->booking_reference,
            'service_identifier' => $this->mission->booking?->service_identifier_display,
            'service_label' => $this->mission->booking?->service_display_name,
            'employee_name' => $this->mission->leadEmployee?->name,
            'status' => $this->mission->status,
            'start_code' => $this->startCode,
        ];
    }
}

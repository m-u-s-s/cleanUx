<?php

namespace App\Notifications;

use App\Models\Mission;
use App\Models\MissionVerificationCode;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** PORTE AU CLIENT LE CODE DE FIN, QUI N'AVAIT AUCUN MOYEN DE L'ATTEINDRE. */
class MissionEndCodeNotification extends Notification
{
    use InteractsWithUserNotificationPreferences;
    use Queueable;

    /**
     * SANS LUI, LE PORTEUR EST AMBIGU.
     *
     * @param  MissionVerificationCode|null  $record  L'enregistrement auquel ce code correspond.
     */
    public function __construct(
        public Mission $mission,
        public string $endCode,
        public ?MissionVerificationCode $record = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels(
            $notifiable,
            'mission_end_code',
            ['database', 'mail'],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre code de fin de mission')
            ->greeting('Bonjour,')
            ->line('Voici le code de fin pour la mission '.$this->mission->booking?->booking_reference.'.')
            ->line('Code de fin de mission : '.$this->endCode)
            ->line('Communiquez-le au prestataire en fin de service pour clôturer l’intervention.')
            ->action('Voir le suivi', url('/dashboard/client/rendezvous'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mission_end_code',
            'mission_id' => $this->mission->id,
            'rendez_vous_id' => $this->mission->booking_id,
            'booking_reference' => $this->mission->booking?->booking_reference,
            'service_label' => $this->mission->booking?->service_display_name,
            'status' => $this->mission->status,
            'end_code' => $this->endCode,
            'code_id' => $this->record?->id,
            'expires_at' => $this->record?->expires_at?->toIso8601String(),
        ];
    }
}

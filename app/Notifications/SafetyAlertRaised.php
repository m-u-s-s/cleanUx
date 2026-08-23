<?php

namespace App\Notifications;

use App\Models\SafetyAlert;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** UNE ALERTE DE SÉCURITÉ VIENT D'ÊTRE DÉCLENCHÉE (E33). */
class SafetyAlertRaised extends Notification
{
    use Queueable;

    public function __construct(
        public SafetyAlert $alerte,
        public User $prestataire,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // `database` en premier : c'est le seul canal qui ne dépend d'aucun tiers.
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $urgence = $this->alerte->level === SafetyAlert::LEVEL_EMERGENCY;

        return (new MailMessage)
            ->subject(($urgence ? '🚨 URGENCE' : '⚠️ Veille').' — '.($this->prestataire->name ?? 'un prestataire'))
            ->greeting($urgence ? 'Alerte d’urgence' : 'Demande de veille')
            ->line(sprintf(
                '%s a déclenché une alerte %s.',
                $this->prestataire->name ?? 'Un prestataire',
                $urgence ? 'd’URGENCE' : 'de veille',
            ))
            ->when($this->alerte->message, fn (MailMessage $mail) => $mail->line('« '.$this->alerte->message.' »'))
            ->when(
                $this->alerte->lat !== null && $this->alerte->lng !== null,
                fn (MailMessage $mail) => $mail->line(sprintf(
                    'Dernière position connue : %s, %s',
                    $this->alerte->lat,
                    $this->alerte->lng,
                )),
            )
            ->line('Ouvrez le centre de sécurité pour suivre et accuser réception.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'safety_alert_id' => $this->alerte->id,
            'level' => $this->alerte->level,
            'user_id' => $this->prestataire->id,
            'user_name' => $this->prestataire->name,
            'lat' => $this->alerte->lat,
            'lng' => $this->alerte->lng,
            'mission_id' => $this->alerte->mission_id,
        ];
    }
}

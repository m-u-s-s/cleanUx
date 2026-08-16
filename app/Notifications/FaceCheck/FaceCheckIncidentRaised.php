<?php

namespace App\Notifications\FaceCheck;

use App\Models\ProviderFaceIncident;
use App\Models\User;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Ce qui arrive dans la boîte d'un administrateur quand un contrôle facial appelle un humain.
 *
 * PAS `ShouldQueue`, comme `SafetyAlertRaised` : une alerte de sécurité qui attend qu'un worker
 * la ramasse est une alerte qui n'arrive pas quand la file est bouchée — et c'est précisément
 * quand quelque chose ne va pas que la file est bouchée.
 *
 * `database` en premier dans `via()` : c'est le seul canal qui ne dépend d'aucun tiers.
 */
class FaceCheckIncidentRaised extends Notification
{
    use InteractsWithUserNotificationPreferences;

    public function __construct(
        public ProviderFaceIncident $incident,
        public User $prestataire,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable, 'face_check_incident', ['database', 'mail']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $critique = $this->incident->severity === ProviderFaceIncident::SEVERITY_CRITICAL;

        $message = (new MailMessage)
            ->subject(($critique ? '🚨 Fraude possible' : '⚠️ Contrôle facial').' — '.$this->prestataire->name)
            ->greeting($critique ? 'Fraude possible sur un contrôle d’identité' : 'Un contrôle facial demande votre attention')
            ->line('Prestataire : '.$this->prestataire->name.' (#'.$this->prestataire->id.')')
            ->line('Motif : '.$this->libelleDuType());

        if (filled($this->incident->message)) {
            $message->line($this->incident->message);
        }

        if ($this->incident->occurrence_count > 1) {
            $message->line('Occurrences : '.$this->incident->occurrence_count);
        }

        return $message->action('Ouvrir la vérification faciale', url('/admin/verification-faciale'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'face_incident_id' => $this->incident->id,
            'type' => $this->incident->type,
            'severity' => $this->incident->severity,
            'occurrence_count' => $this->incident->occurrence_count,
            'user_id' => $this->prestataire->id,
            'user_name' => $this->prestataire->name,
            'message' => $this->incident->message,
        ];
    }

    private function libelleDuType(): string
    {
        return match ($this->incident->type) {
            ProviderFaceIncident::TYPE_PROVIDER_REPORT => 'le prestataire signale que le contrôle ne fonctionne pas',
            ProviderFaceIncident::TYPE_REPEATED_ABANDON => 'contrôles abandonnés à répétition',
            ProviderFaceIncident::TYPE_REPEATED_FAILURE => 'contrôles échoués à répétition',
            ProviderFaceIncident::TYPE_LIVENESS_FAIL => 'échec de détection de vivacité (photo d’une photo ?)',
            ProviderFaceIncident::TYPE_ID_MISMATCH => 'le visage ne correspond pas à la pièce d’identité',
            default => $this->incident->type,
        };
    }
}

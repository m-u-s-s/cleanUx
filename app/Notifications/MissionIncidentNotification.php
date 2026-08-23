<?php

namespace App\Notifications;

use App\Models\MissionIncident;
use App\Notifications\Channels\WebPushChannel;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** « Il y a un problème chez vous, maintenant. */
class MissionIncidentNotification extends Notification
{
    use InteractsWithUserNotificationPreferences;
    use Queueable;

    public function __construct(public MissionIncident $incident) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels(
            $notifiable,
            'mission_incident',
            ['database', 'mail', 'sms', WebPushChannel::class],
        );
    }

    public function toSms(object $notifiable): string
    {
        return sprintf(
            'Brio : imprevu signale sur votre intervention (%s). Consultez le suivi pour repondre.',
            MissionIncident::libelleType($this->incident->incident_type),
        );
    }

    public function smsIdempotencyKey(object $notifiable): string
    {
        return 'mission:incident:'.$this->incident->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Imprévu sur votre intervention',
            'body' => (string) ($this->incident->title ?: MissionIncident::libelleType($this->incident->incident_type)),
            'url' => '/dashboard/client/rendez-vous',
            'tag' => 'mission-incident-'.$this->incident->id,
            'requireInteraction' => true,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Imprévu signalé sur votre intervention')
            ->greeting('Bonjour,')
            ->line('Votre prestataire vient de signaler un imprévu : '
                .MissionIncident::libelleType($this->incident->incident_type).'.');

        if ($this->incident->description) {
            $message->line('« '.$this->incident->description.' »');
        }

        return $message->action('Voir le suivi', url('/dashboard/client/rendez-vous'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mission_incident',
            'mission_id' => $this->incident->mission_id,
            'incident_id' => $this->incident->id,
            'incident_type' => $this->incident->incident_type,
            'severity' => $this->incident->severity,
            'title' => $this->incident->title,
            'description' => $this->incident->description,
        ];
    }
}

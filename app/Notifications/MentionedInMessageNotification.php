<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification "tu as été mentionné dans un message".
 *
 * Stockée en base (badge persistant) ET envoyée par email si l'utilisateur
 * n'est pas en ligne ou si la mention est plus vieille que X minutes
 * (logique de digestion à affiner Phase 4.1).
 */
class MentionedInMessageNotification extends Notification
{
    use Queueable;

    public function __construct(public Message $message) {}

    public function via(object $notifiable): array
    {
        // 'database' toujours, 'mail' si user a opté
        $channels = ['database'];

        // Optionnel : si tu as un PreferenceService, le consulter ici
        // if ($notifiable->wantsEmailFor('mentions')) $channels[] = 'mail';

        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        $sender = $this->message->sender;

        return [
            'type'          => 'mention',
            'message_id'    => $this->message->id,
            'channel_id'    => $this->message->channel_id,
            'channel_name'  => $this->message->channel?->name,
            'sender_id'     => $sender?->id,
            'sender_name'   => $sender?->name,
            'preview'       => str($this->message->content)->limit(160)->toString(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $sender = $this->message->sender;

        return (new MailMessage)
            ->subject("Vous avez été mentionné dans CleanUx")
            ->greeting("Bonjour {$notifiable->name},")
            ->line(($sender->name ?? 'Quelqu’un') . " vous a mentionné dans #" . ($this->message->channel?->name ?? 'un canal') . " :")
            ->line('"' . str($this->message->content)->limit(200) . '"')
            ->action('Voir le message', url('/team/channels?focus=' . $this->message->id));
    }
}

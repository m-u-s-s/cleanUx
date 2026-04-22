<?php

namespace App\Notifications;

use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminDigestNotification extends Notification
{
    use Queueable;
    use InteractsWithUserNotificationPreferences;

    public function __construct(
        public array $items = [],
        public string $title = 'Synthèse automatique des alertes métier',
        public ?string $actionUrl = null
    ) {
    }

    public function via($notifiable): array
    {
        return $this->preferredChannels($notifiable, 'admin_digest', ['mail', 'database']);
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('CleanUx · ' . $this->title)
            ->line('Voici les points nécessitant votre attention.');

        foreach ($this->items as $item) {
            $mail->line('• ' . $item);
        }

        return $mail
            ->action('Ouvrir le dashboard admin', $this->actionUrl ?: url('/admin/dashboard'))
            ->line('Cette synthèse est générée automatiquement.');
    }

    public function toArray($notifiable): array
    {
        return $this->basePayload([
            'type' => 'admin',
            'severity' => 'info',
            'title' => $this->title,
            'message' => 'Synthèse automatique des alertes métier disponible.',
            'items' => $this->items,
            'action_url' => $this->actionUrl ?: url('/admin/dashboard'),
        ]);
    }
}

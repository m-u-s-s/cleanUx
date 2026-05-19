<?php

namespace App\Notifications\Gdpr;

use App\Models\GdprDataRequest;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GdprExportReadyNotification extends Notification
{
    use Queueable;
    use InteractsWithUserNotificationPreferences;

    public function __construct(public GdprDataRequest $request)
    {
    }

    public function via($notifiable): array
    {
        return $this->preferredChannels($notifiable, 'gdpr_export_ready', ['mail', 'database']);
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('CleanUx · Votre export de données est prêt')
            ->greeting('Bonne nouvelle !')
            ->line('Votre export RGPD ' . $this->request->reference . ' est disponible au téléchargement.')
            ->line('Disponible jusqu\'au ' . optional($this->request->expires_at)->format('d/m/Y'))
            ->action('Télécharger mes données', url('/dashboard/client/donnees'));
    }

    public function toArray($notifiable): array
    {
        return $this->basePayload([
            'type' => 'gdpr_export_ready',
            'severity' => 'success',
            'title' => 'Export RGPD prêt',
            'message' => 'Votre export ' . $this->request->reference . ' est disponible',
            'request_id' => $this->request->id,
            'expires_at' => $this->request->expires_at,
        ]);
    }
}

<?php

namespace App\Notifications\Disputes;

use App\Models\ComplaintCase;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DisputeOpenedNotification extends Notification
{
    use Queueable;
    use InteractsWithUserNotificationPreferences;

    public function __construct(public ComplaintCase $case)
    {
    }

    public function via($notifiable): array
    {
        return $this->preferredChannels($notifiable, 'dispute_opened', ['mail', 'database']);
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('CleanUx · Votre réclamation ' . $this->case->reference . ' a été enregistrée')
            ->greeting('Bonjour,')
            ->line('Votre réclamation a bien été enregistrée.')
            ->line('Référence : ' . $this->case->reference)
            ->line('Catégorie : ' . $this->case->category)
            ->line('Priorité : ' . $this->case->priority)
            ->line('Notre équipe vous répond sous ' . ($this->case->sla_policy ?? '24h') . '.')
            ->action('Voir ma réclamation', url('/dashboard/client/litiges'));
    }

    public function toArray($notifiable): array
    {
        return $this->basePayload([
            'type' => 'dispute_opened',
            'severity' => 'info',
            'title' => 'Réclamation enregistrée',
            'message' => $this->case->reference . ' — ' . $this->case->subject,
            'dispute_id' => $this->case->id,
            'reference' => $this->case->reference,
            'sla_policy' => $this->case->sla_policy,
            'action_url' => '/dashboard/client/litiges',
        ]);
    }
}

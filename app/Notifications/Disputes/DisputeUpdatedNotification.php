<?php

namespace App\Notifications\Disputes;

use App\Models\ComplaintCase;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DisputeUpdatedNotification extends Notification
{
    use Queueable;
    use InteractsWithUserNotificationPreferences;

    public function __construct(public ComplaintCase $case)
    {
    }

    public function via($notifiable): array
    {
        return $this->preferredChannels($notifiable, 'dispute_updated', ['mail', 'database']);
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('CleanUx · Mise à jour de votre réclamation ' . $this->case->reference)
            ->line('Votre réclamation a évolué.')
            ->line('Statut : ' . $this->case->status)
            ->action('Voir le détail', url('/dashboard/client/litiges'));
    }

    public function toArray($notifiable): array
    {
        return $this->basePayload([
            'type' => 'dispute_updated',
            'severity' => 'info',
            'title' => 'Réclamation mise à jour',
            'message' => $this->case->reference . ' — ' . $this->case->status,
            'dispute_id' => $this->case->id,
            'reference' => $this->case->reference,
            'status' => $this->case->status,
            'action_url' => '/dashboard/client/litiges',
        ]);
    }
}

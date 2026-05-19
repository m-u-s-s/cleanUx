<?php

namespace App\Notifications\Disputes;

use App\Models\ComplaintCase;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DisputeResolvedNotification extends Notification
{
    use Queueable;
    use InteractsWithUserNotificationPreferences;

    public function __construct(public ComplaintCase $case)
    {
    }

    public function via($notifiable): array
    {
        return $this->preferredChannels($notifiable, 'dispute_resolved', ['mail', 'database']);
    }

    public function toMail($notifiable): MailMessage
    {
        $resolution = $this->case->appliedResolution()->first()
            ?? $this->case->resolutions()->latest()->first();

        $mail = (new MailMessage)
            ->subject('CleanUx · Votre réclamation ' . $this->case->reference . ' est résolue')
            ->greeting('Bonne nouvelle !')
            ->line('Votre réclamation a été résolue.');

        if ($resolution) {
            $mail->line('Résolution : ' . $resolution->resolution_type);
            if ($resolution->amount) {
                $mail->line(sprintf(
                    'Montant : %.2f %s',
                    (float) $resolution->amount,
                    $resolution->currency,
                ));
            }
            if ($resolution->explanation) {
                $mail->line($resolution->explanation);
            }
        }

        return $mail->action('Voir le détail', url('/dashboard/client/litiges'));
    }

    public function toArray($notifiable): array
    {
        $resolution = $this->case->appliedResolution()->first()
            ?? $this->case->resolutions()->latest()->first();

        return $this->basePayload([
            'type' => 'dispute_resolved',
            'severity' => 'success',
            'title' => 'Réclamation résolue',
            'message' => $this->case->reference . ($resolution ? ' — ' . $resolution->resolution_type : ''),
            'dispute_id' => $this->case->id,
            'reference' => $this->case->reference,
            'resolution_type' => $resolution?->resolution_type,
            'amount' => $resolution?->amount !== null ? (float) $resolution->amount : null,
            'action_url' => '/dashboard/client/litiges',
        ]);
    }
}

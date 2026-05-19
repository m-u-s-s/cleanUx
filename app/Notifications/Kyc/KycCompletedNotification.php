<?php

namespace App\Notifications\Kyc;

use App\Models\KycVerification;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KycCompletedNotification extends Notification
{
    use Queueable;
    use InteractsWithUserNotificationPreferences;

    public function __construct(public KycVerification $verification)
    {
    }

    public function via($notifiable): array
    {
        return $this->preferredChannels($notifiable, 'kyc_completed', ['mail', 'database']);
    }

    public function toMail($notifiable): MailMessage
    {
        $approved = $this->verification->isApproved();

        $mail = (new MailMessage)
            ->subject($approved
                ? 'CleanUx · Votre vérification d\'identité est validée'
                : 'CleanUx · Votre vérification d\'identité nécessite une action');

        if ($approved) {
            $mail->greeting('Bienvenue !')
                ->line('Votre vérification d\'identité (KYC) a été validée avec succès.')
                ->line('Vous pouvez maintenant accepter des missions.')
                ->action('Accéder à mon dashboard', url('/dashboard/employe'));
        } else {
            $mail->greeting('Bonjour,')
                ->line('Votre vérification d\'identité nécessite un complément ou n\'a pas pu être validée.');

            if ($this->verification->rejection_reason) {
                $mail->line('Raison : ' . $this->verification->rejection_reason);
            }

            $mail->action('Compléter ma vérification', url('/dashboard/employe'));
        }

        return $mail;
    }

    public function toArray($notifiable): array
    {
        return $this->basePayload([
            'type' => 'kyc_completed',
            'severity' => $this->verification->isApproved() ? 'success' : 'warning',
            'title' => $this->verification->isApproved()
                ? 'Vérification d\'identité validée'
                : 'Vérification d\'identité à compléter',
            'message' => $this->verification->rejection_reason
                ?? 'Votre vérification KYC est ' . $this->verification->status,
            'verification_id' => $this->verification->id,
            'decision' => $this->verification->decision,
            'status' => $this->verification->status,
        ]);
    }
}

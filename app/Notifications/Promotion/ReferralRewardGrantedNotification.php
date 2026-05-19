<?php

namespace App\Notifications\Promotion;

use App\Models\ReferralReward;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReferralRewardGrantedNotification extends Notification
{
    use Queueable;
    use InteractsWithUserNotificationPreferences;

    public function __construct(public ReferralReward $reward)
    {
    }

    public function via($notifiable): array
    {
        return $this->preferredChannels($notifiable, 'referral_reward_granted', ['mail', 'database']);
    }

    public function toMail($notifiable): MailMessage
    {
        $amount = number_format((float) $this->reward->amount, 2, ',', ' ');
        $currency = $this->reward->currency ?? 'EUR';

        $title = $this->reward->role === ReferralReward::ROLE_REFERRER
            ? 'Votre récompense de parrainage est créditée'
            : 'Votre bonus de bienvenue est crédité';

        return (new MailMessage)
            ->subject('CleanUx · '.$title)
            ->greeting('Bonne nouvelle !')
            ->line($title.' : '.$amount.' '.$currency.'.')
            ->line('Le crédit sera automatiquement déduit de votre prochaine réservation.')
            ->action('Réserver maintenant', url('/dashboard'));
    }

    public function toArray($notifiable): array
    {
        return $this->basePayload([
            'type' => 'referral_reward',
            'severity' => 'success',
            'title' => 'Récompense de parrainage créditée',
            'message' => sprintf(
                'Vous avez reçu %.2f %s en crédit (%s).',
                (float) $this->reward->amount,
                $this->reward->currency ?? 'EUR',
                $this->reward->role,
            ),
            'reward_id' => $this->reward->id,
            'referral_id' => $this->reward->referral_id,
            'amount' => (float) $this->reward->amount,
            'currency' => $this->reward->currency,
            'role' => $this->reward->role,
            'reward_type' => $this->reward->reward_type,
            'action_url' => url('/dashboard'),
        ]);
    }
}

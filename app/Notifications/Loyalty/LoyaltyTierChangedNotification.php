<?php

namespace App\Notifications\Loyalty;

use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTier;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoyaltyTierChangedNotification extends Notification
{
    use Queueable;
    use InteractsWithUserNotificationPreferences;

    public function __construct(
        public LoyaltyAccount $account,
        public ?LoyaltyTier $previousTier,
        public LoyaltyTier $newTier,
        public bool $isUpgrade,
    ) {}

    public function via($notifiable): array
    {
        return $this->preferredChannels($notifiable, 'loyalty_tier_changed', ['mail', 'database']);
    }

    public function toMail($notifiable): MailMessage
    {
        if ($this->isUpgrade) {
            return (new MailMessage)
                ->subject('CleanUx · Vous passez au niveau ' . $this->newTier->name . ' ' . $this->newTier->icon)
                ->greeting('Félicitations !')
                ->line('Vous venez de passer au niveau ' . $this->newTier->name . '.')
                ->line('Vos avantages :')
                ->lines(array_map(fn ($b) => '• ' . $b, $this->newTier->benefits ?? []))
                ->action('Voir mon programme', url('/dashboard/client/fidelite'));
        }

        return (new MailMessage)
            ->subject('CleanUx · Mise à jour de votre niveau fidélité')
            ->line('Votre niveau passe à ' . $this->newTier->name . '.')
            ->line('Continuez à profiter de nos services pour remonter !')
            ->action('Voir mon programme', url('/dashboard/client/fidelite'));
    }

    public function toArray($notifiable): array
    {
        return $this->basePayload([
            'type' => $this->isUpgrade ? 'loyalty_tier_upgraded' : 'loyalty_tier_downgraded',
            'severity' => $this->isUpgrade ? 'success' : 'info',
            'title' => $this->isUpgrade
                ? 'Niveau ' . $this->newTier->name . ' atteint !'
                : 'Niveau ajusté à ' . $this->newTier->name,
            'message' => 'De ' . ($this->previousTier?->name ?? 'aucun') . ' vers ' . $this->newTier->name,
            'previous_tier' => $this->previousTier?->slug,
            'new_tier' => $this->newTier->slug,
            'is_upgrade' => $this->isUpgrade,
        ]);
    }
}

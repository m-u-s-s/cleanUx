<?php

namespace App\Notifications\Promotion;

use App\Models\PromoCodeRedemption;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PromoCodeAppliedNotification extends Notification
{
    use Queueable;
    use InteractsWithUserNotificationPreferences;

    public function __construct(public PromoCodeRedemption $redemption)
    {
    }

    public function via($notifiable): array
    {
        return $this->preferredChannels($notifiable, 'promo_code_applied', ['database']);
    }

    public function toMail($notifiable): MailMessage
    {
        $discount = number_format((float) $this->redemption->discount_amount, 2, ',', ' ');
        $currency = $this->redemption->currency ?? 'EUR';
        $code = $this->redemption->promoCode?->code ?? '';

        return (new MailMessage)
            ->subject('CleanUx · Code promo appliqué')
            ->line('Votre code promo '.$code.' a été appliqué.')
            ->line('Réduction : '.$discount.' '.$currency)
            ->action('Voir mes réservations', url('/dashboard'));
    }

    public function toArray($notifiable): array
    {
        return $this->basePayload([
            'type' => 'promo_code_applied',
            'severity' => 'success',
            'title' => 'Code promo appliqué',
            'message' => sprintf(
                'Code %s — réduction de %.2f %s appliquée.',
                $this->redemption->promoCode?->code ?? '?',
                (float) $this->redemption->discount_amount,
                $this->redemption->currency ?? 'EUR',
            ),
            'redemption_id' => $this->redemption->id,
            'promo_code_id' => $this->redemption->promo_code_id,
            'booking_id' => $this->redemption->booking_id,
            'discount_amount' => (float) $this->redemption->discount_amount,
            'currency' => $this->redemption->currency,
        ]);
    }
}

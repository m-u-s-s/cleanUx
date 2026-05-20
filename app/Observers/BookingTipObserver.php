<?php

namespace App\Observers;

use App\Models\BookingTip;
use App\Models\PushNotification;
use App\Services\Push\PushService;
use Illuminate\Support\Facades\Log;

/**
 * Réagit aux changements de status d'un BookingTip :
 *   - pending → charged : notif push au provider ("vous avez reçu un tip de X€")
 *   - charged → paid_out : notif push au provider (payout fait)
 *
 * Tout soft-fail : ne bloque jamais le flow business.
 */
class BookingTipObserver
{
    public function updated(BookingTip $tip): void
    {
        if (! $tip->wasChanged('status')) {
            return;
        }

        $newStatus = $tip->status;
        $oldStatus = $tip->getOriginal('status');

        if ($oldStatus === BookingTip::STATUS_PENDING && $newStatus === BookingTip::STATUS_CHARGED) {
            $this->notifyProviderOfTip($tip);
        }

        if ($oldStatus === BookingTip::STATUS_CHARGED && $newStatus === BookingTip::STATUS_PAID_OUT) {
            $this->notifyProviderOfPayout($tip);
        }
    }

    protected function notifyProviderOfTip(BookingTip $tip): void
    {
        try {
            if (! class_exists(PushService::class) || ! $tip->provider) {
                return;
            }
            $amount = number_format($tip->amount_cents / 100, 2, ',', ' ');
            app(PushService::class)->dispatchToUser(
                user: $tip->provider,
                title: '💰 Pourboire reçu',
                body: "Vous avez reçu un pourboire de {$amount} {$tip->currency} sur la mission #{$tip->booking_id}.",
                data: [
                    'type' => 'tip.received',
                    'tip_id' => $tip->id,
                    'booking_id' => $tip->booking_id,
                ],
                category: PushNotification::CATEGORY_TRANSACTIONAL,
                idempotencyKey: 'tip_received_' . $tip->id,
                source: $tip,
            );
        } catch (\Throwable $e) {
            Log::warning('[tips_push] notifyProviderOfTip failed', [
                'tip_id' => $tip->id, 'error' => $e->getMessage(),
            ]);
        }
    }

    protected function notifyProviderOfPayout(BookingTip $tip): void
    {
        try {
            if (! class_exists(PushService::class) || ! $tip->provider) {
                return;
            }
            $amount = number_format($tip->amount_cents / 100, 2, ',', ' ');
            app(PushService::class)->dispatchToUser(
                user: $tip->provider,
                title: '✅ Pourboire versé',
                body: "Votre pourboire de {$amount} {$tip->currency} a été versé.",
                data: [
                    'type' => 'tip.paid_out',
                    'tip_id' => $tip->id,
                ],
                category: PushNotification::CATEGORY_TRANSACTIONAL,
                idempotencyKey: 'tip_paid_out_' . $tip->id,
                source: $tip,
            );
        } catch (\Throwable $e) {
            Log::warning('[tips_push] notifyProviderOfPayout failed', [
                'tip_id' => $tip->id, 'error' => $e->getMessage(),
            ]);
        }
    }
}

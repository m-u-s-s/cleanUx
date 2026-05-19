<?php

namespace App\Notifications\Channels;

use App\Services\Notifications\SmsService;
use Illuminate\Notifications\Notification;

/**
 * Channel Laravel "sms" — permet aux Notifications d'envoyer un SMS.
 *
 * Usage dans une Notification:
 *   public function via($notifiable): array {
 *       return ['mail', 'database', 'sms'];
 *   }
 *
 *   public function toSms($notifiable): string {
 *       return 'Votre mission commence dans 30 min !';
 *   }
 *
 * Le notifiable doit exposer `routeNotificationForSms()` ou avoir `phone`.
 */
class SmsChannel
{
    public function __construct(protected SmsService $smsService)
    {
    }

    public function send($notifiable, Notification $notification): mixed
    {
        $body = method_exists($notification, 'toSms')
            ? $notification->toSms($notifiable)
            : null;

        if (! $body) {
            return null;
        }

        $phone = $this->resolvePhone($notifiable, $notification);
        if (! $phone) {
            return null;
        }

        $idempotencyKey = method_exists($notification, 'smsIdempotencyKey')
            ? (string) $notification->smsIdempotencyKey($notifiable)
            : null;

        $category = method_exists($notification, 'smsCategory')
            ? (string) $notification->smsCategory()
            : \App\Models\SmsMessage::CATEGORY_TRANSACTIONAL;

        return $this->smsService->dispatch(
            toPhone: $phone,
            body: (string) $body,
            user: $notifiable instanceof \App\Models\User ? $notifiable : null,
            category: $category,
            idempotencyKey: $idempotencyKey,
            locale: $notifiable->preferredLocale() ?? null,
        );
    }

    protected function resolvePhone($notifiable, Notification $notification): ?string
    {
        if (method_exists($notifiable, 'routeNotificationForSms')) {
            $phone = $notifiable->routeNotificationForSms($notification);
            if ($phone) {
                return (string) $phone;
            }
        }

        return $notifiable->phone ?? null;
    }
}

<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Phase 8 — Exemple de notification utilisant le canal webpush.
 *
 * Pattern à suivre pour ajouter le canal webpush à une notification existante :
 *   1. Ajouter `WebPushChannel::class` dans le tableau via()
 *   2. Implémenter une méthode toWebPush($notifiable) qui retourne le payload
 *
 * NB : pour ajouter webpush aux notifications EXISTANTES (EmployeArriveNotification,
 * DemandeFeedbackNotification, etc.), il suffit de répliquer ces 2 changements.
 * Pas besoin de recréer la notification.
 */
class BookingReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Booking $booking,
    ) {}

    public function via(object $notifiable): array
    {
        return [
            'database',
            'mail',
            WebPushChannel::class,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Rappel : votre rendez-vous demain')
            ->greeting('Bonjour,')
            ->line("Pour rappel, vous avez un rendez-vous demain : {$this->booking->booking_reference}")
            ->action('Voir le rendez-vous', url('/dashboard/client/rendezvous'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_id'        => $this->booking->id,
            'booking_reference' => $this->booking->booking_reference,
            'scheduled_date'    => $this->booking->scheduled_date,
        ];
    }

    /**
     * Phase 8 — Payload pour Web Push.
     *
     * Retourne un tableau avec :
     *   - title (required)
     *   - body  (required)
     *   - url   (page à ouvrir au clic)
     *   - tag   (déduplication des notifs identiques)
     *   - icon, badge, image (optionnels)
     *   - requireInteraction (notif persistante jusqu'au clic)
     *   - actions (boutons dans la notif)
     */
    public function toWebPush(object $notifiable): array
    {
        $service = $this->booking->serviceCatalog?->name ?? 'Intervention';
        $time = $this->booking->scheduled_time
            ? \Carbon\Carbon::parse($this->booking->scheduled_time)->format('H:i')
            : '';

        return [
            'title' => 'Rappel : RDV demain',
            'body'  => "{$service}" . ($time ? " à {$time}" : ''),
            'url'   => '/dashboard/client/rendezvous',
            'tag'   => 'booking-reminder-' . $this->booking->id,
            'icon'  => '/icons/icon-192.png',
            'badge' => '/icons/badge-72.png',
            'requireInteraction' => false,
            'data' => [
                'booking_id' => $this->booking->id,
            ],
            'actions' => [
                [
                    'action' => 'view',
                    'title'  => 'Voir',
                ],
            ],
        ];
    }
}

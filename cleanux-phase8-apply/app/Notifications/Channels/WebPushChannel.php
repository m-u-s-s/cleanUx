<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Services\Push\WebPushSender;
use Illuminate\Notifications\Notification;

/**
 * Phase 8 — Channel Laravel pour envoyer des notifications via Web Push.
 *
 * Pour utiliser :
 *   class MaNotification extends Notification {
 *       public function via($notifiable): array {
 *           return ['database', 'mail', \App\Notifications\Channels\WebPushChannel::class];
 *       }
 *
 *       public function toWebPush($notifiable): array {
 *           return [
 *               'title' => 'Votre prestataire arrive',
 *               'body'  => "Mission CUX-ABC123",
 *               'url'   => route('client.rendezvous.index'),
 *               'tag'   => 'mission-' . $this->mission->id,
 *           ];
 *       }
 *   }
 *
 * Pas besoin de bind explicite : Laravel détecte automatiquement les channels
 * référencés par leur classe complète.
 */
class WebPushChannel
{
    public function __construct(
        protected WebPushSender $sender,
    ) {}

    public function send($notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWebPush')) {
            return;
        }

        $payload = $notification->toWebPush($notifiable);
        if (empty($payload)) {
            return;
        }

        if (! method_exists($notifiable, 'getKey')) {
            return;
        }

        $userId = $notifiable->getKey();

        $user = $notifiable instanceof User
            ? $notifiable
            : User::find($userId);

        if ($user) {
            $this->sender->sendToUser($user, $payload);
        }
    }
}

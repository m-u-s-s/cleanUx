<?php

namespace App\Notifications\Channels;

use App\Services\Push\WebPushSender;
use Illuminate\Notifications\Notification;

/**
 * Phase 8 — Channel Laravel pour envoyer des notifications via Web Push.
 *
 * Une Notification Laravel ajoute 'webpush' dans son tableau via() et implémente
 * une méthode toWebPush($notifiable) qui retourne le payload :
 *
 *   public function via($notifiable): array
 *   {
 *       return ['database', 'mail', 'webpush'];
 *   }
 *
 *   public function toWebPush($notifiable): array
 *   {
 *       return [
 *           'title' => 'Votre prestataire arrive',
 *           'body'  => 'Mission CUX-ABC123',
 *           'url'   => route('client.rendezvous.index'),
 *           'tag'   => 'mission-' . $this->mission->id,
 *       ];
 *   }
 *
 * Enregistrement automatique : Laravel détecte les channels via leur classe.
 * Pas besoin de bind explicite tant qu'on les référence par classe complète.
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

        // $notifiable est typiquement un User (peut aussi être un autre modèle)
        if (method_exists($notifiable, 'getKey')) {
            $userId = $notifiable->getKey();
        } else {
            return;
        }

        $user = $notifiable instanceof \App\Models\User
            ? $notifiable
            : \App\Models\User::find($userId);

        if ($user) {
            $this->sender->sendToUser($user, $payload);
        }
    }
}

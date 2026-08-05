<?php

namespace App\Notifications;

use App\Models\ConversationMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class NewConversationMessageNotification extends Notification
{
    use Queueable;

    public function __construct(public ConversationMessage $message) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'conversation_message',
            'title' => 'Nouveau message',
            'message' => $this->message->sender?->name.' vous a envoyé un message.',
            'conversation_id' => $this->message->conversation_id,
            /*
             * LE LIEN MÈNE ENFIN AU MESSAGE (2026-08-05).
             *
             * `action_url` renvoyait vers le tableau de bord client. Le destinataire lisait
             * « Nouveau message », cliquait, et arrivait sur son accueil — sans aucun chemin vers
             * la conversation, dont l'identifiant était pourtant JUSTE AU-DESSUS dans cette même
             * charge utile. `client.conversations.show` n'a pas de page liste : cette notification
             * est sa seule porte, et elle ne l'ouvrait pas.
             */
            'action_url' => Route::has('client.conversations.show') && $this->message->conversation_id
                ? route('client.conversations.show', $this->message->conversation_id)
                : route('client.dashboard'),
        ];
    }
}

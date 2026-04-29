<?php

namespace App\Notifications;

use App\Models\ConversationMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

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
            'action_url' => url('/dashboard/client'),
        ];
    }
}
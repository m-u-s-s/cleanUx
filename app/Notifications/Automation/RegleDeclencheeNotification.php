<?php

namespace App\Notifications\Automation;

use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

/** Ce qu'un administrateur recoit quand une regle le previent. */
class RegleDeclencheeNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $message,
        protected Model $entite,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'automation',
            'message' => $this->message,
            'entite_type' => $this->entite->getMorphClass(),
            'entite_id' => $this->entite->getKey(),
        ];
    }
}

<?php

namespace App\Notifications;

use App\Models\Feedback;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FeedbackAjouteNotification extends Notification
{
    use Queueable;
    use InteractsWithUserNotificationPreferences;

    public function __construct(public Feedback $feedback)
    {
    }

    public function via($notifiable)
    {
        return $this->preferredChannels($notifiable, 'feedback_added', ['database']);
    }

    public function toDatabase($notifiable)
    {
        return $this->basePayload([
            'type' => 'feedback',
            'severity' => 'info',
            'title' => 'Nouveau feedback client',
            'message' => 'Un nouveau feedback client a été enregistré.',
            'client' => $this->feedback->client->name,
            'employe' => $this->feedback->rendezVous->employe->name ?? '—',
            'note' => $this->feedback->note,
            'commentaire' => $this->feedback->commentaire,
            'feedback_id' => $this->feedback->id,
            'rdv_id' => $this->feedback->rendezVous->id ?? null,
            'created_at' => now()->toDateTimeString(),
            'action_url' => url('/admin/feedbacks'),
        ]);
    }
}

<?php

namespace App\Services\Notifications;

use App\Models\Booking;
use App\Notifications\FeedbackInviteNotification;
use App\Notifications\RendezVousReminderNotification;

class SmartNotificationService
{
    public function send24hReminder(Booking $rdv): void
    {
        if (! $rdv->client || $rdv->rappel_24h_envoye_at) {
            return;
        }

        $rdv->client->notify(new RendezVousReminderNotification($rdv, '24h'));

        $rdv->update([
            'rappel_24h_envoye_at' => now(),
        ]);
    }

    public function send2hReminder(Booking $rdv): void
    {
        if (! $rdv->client || $rdv->rappel_2h_envoye_at) {
            return;
        }

        $rdv->client->notify(new RendezVousReminderNotification($rdv, '2h'));

        $rdv->update([
            'rappel_2h_envoye_at' => now(),
        ]);
    }

    public function sendFeedbackInvite(Booking $rdv): void
    {
        if (! $rdv->client || $rdv->feedback_demande_envoye_at) {
            return;
        }

        $rdv->client->notify(new FeedbackInviteNotification($rdv));

        $rdv->update([
            'feedback_demande_envoye_at' => now(),
        ]);
    }
}
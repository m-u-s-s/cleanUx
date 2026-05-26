<?php

namespace App\Listeners\Disputes;

use App\Events\Disputes\DisputeOpened;
use App\Notifications\Disputes\DisputeOpenedNotification;

class NotifyOnDisputeOpened
{
    public function handle(DisputeOpened $event): void
    {
        $case = $event->case;
        if ($case->client) {
            $case->client->notify(new DisputeOpenedNotification($case));
        }
    }
}

<?php

namespace App\Listeners\Rating;

use App\Events\Rating\RatingSubmitted;
use App\Notifications\Rating\RatingReceivedNotification;

class NotifyProviderOnRating
{
    public function handle(RatingSubmitted $event): void
    {
        $feedback = $event->feedback;
        if ($feedback->provider) {
            $feedback->provider->notify(new RatingReceivedNotification($feedback));
        }
    }
}

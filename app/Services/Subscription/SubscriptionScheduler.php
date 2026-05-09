<?php

namespace App\Services\Subscription;

use App\Models\ClientSubscription;
use App\Models\Booking;
use Carbon\Carbon;

class SubscriptionScheduler
{
    public function generateUpcomingBookings(): void
    {
        $subs = ClientSubscription::where('status', 'active')->get();

        foreach ($subs as $sub) {

            $nextDate = Carbon::now()
                ->next($sub->day_of_week);

            // éviter doublons
            $exists = Booking::where('client_id', $sub->client_id)
                ->whereDate('date', $nextDate)
                ->where('heure', $sub->heure)
                ->exists();

            if ($exists) continue;

            Booking::create([
                'client_id' => $sub->client_id,
                'service_zone_id' => $sub->service_zone_id,
                'service_catalog_id' => $sub->service_catalog_id,
                'date' => $nextDate,
                'heure' => $sub->heure,
                'status' => 'en_attente',
                'devis_estime' => $sub->discounted_price,
                'is_recurring' => true,
                'subscription_id' => $sub->id,
            ]);
        }
    }
}
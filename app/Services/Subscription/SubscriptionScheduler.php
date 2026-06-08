<?php

namespace App\Services\Subscription;

use App\Models\Booking;
use App\Models\ClientSubscription;
use Carbon\Carbon;

class SubscriptionScheduler
{
    public function generateUpcomingBookings(): void
    {
        $subs = ClientSubscription::where('status', 'active')->get();
        if ($subs->isEmpty()) {
            return;
        }

        // Compute each subscription's next occurrence once. day_of_week may come back from the
        // DB as a numeric string ("1"); Carbon::next() needs an int day constant for that
        // (passing the string makes it try modify("next 1") and throw on PHP 8.5+).
        $targets = $subs->map(fn ($sub) => [
            'sub' => $sub,
            'date' => Carbon::now()->next(
                is_numeric($sub->day_of_week) ? (int) $sub->day_of_week : $sub->day_of_week
            ),
        ]);

        // L12 — preload all potentially-conflicting bookings in ONE query instead of an
        // exists() per subscription, then dedupe in memory (also against rows created in this
        // same run). Uses an index-friendly date-range filter (M20 index) and matches the
        // exact slot in PHP via toDateString() to sidestep the SQLite date-with-time gotcha.
        $clientIds = $subs->pluck('client_id')->filter()->unique()->values()->all();

        $seen = Booking::query()
            ->whereIn('client_id', $clientIds)
            ->where('date', '>=', Carbon::now()->toDateString())
            ->get(['client_id', 'date', 'heure'])
            ->reduce(function (array $carry, Booking $b) {
                $carry[$this->slotKey($b->client_id, Carbon::parse($b->date)->toDateString(), $b->heure)] = true;

                return $carry;
            }, []);

        foreach ($targets as $target) {
            $sub = $target['sub'];
            $key = $this->slotKey($sub->client_id, $target['date']->toDateString(), $sub->heure);

            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true; // prevent duplicates within this same run

            Booking::create([
                'client_id' => $sub->client_id,
                'service_zone_id' => $sub->service_zone_id,
                'service_catalog_id' => $sub->service_catalog_id,
                'date' => $target['date'],
                'heure' => $sub->heure,
                'status' => 'en_attente',
                'devis_estime' => $sub->discounted_price,
                'is_recurring' => true,
                'subscription_id' => $sub->id,
            ]);
        }
    }

    private function slotKey(int|string|null $clientId, string $date, ?string $heure): string
    {
        return $clientId.'|'.$date.'|'.$heure;
    }
}

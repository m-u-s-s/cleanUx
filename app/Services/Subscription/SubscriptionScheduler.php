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
                /*
                 * `is_recurrent`, PAS `is_recurring` : la colonne porte le premier nom, et le second
                 * était écarté en silence par Eloquent. Toutes les réservations nées d'un abonnement
                 * naissaient donc NON marquées comme récurrentes — une faute de frappe qu'aucun test
                 * ne pouvait voir, puisque rien ne signale un attribut jeté.
                 *
                 * `subscription_id` a disparu d'ici : `bookings` ne porte pas cette colonne et rien
                 * ne la lit. La déduplication de cet ordonnanceur repose sur le créneau
                 * (client + date + heure), pas sur ce lien. Rattacher explicitement une réservation
                 * à son abonnement reste à faire, et demanderait une colonne.
                 */
                'is_recurrent' => true,
            ]);
        }
    }

    private function slotKey(int|string|null $clientId, string $date, ?string $heure): string
    {
        return $clientId.'|'.$date.'|'.$heure;
    }
}

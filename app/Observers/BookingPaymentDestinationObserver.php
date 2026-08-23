<?php

namespace App\Observers;

use App\Models\Booking;
use Illuminate\Validation\ValidationException;

/** Empêche une réservation de changer de professionnel pendant qu'une somme est bloquée pour l'ancien. */
class BookingPaymentDestinationObserver
{
    public function updating(Booking $booking): void
    {
        if (! $booking->isDirty('employe_id')) {
            return;
        }

        // LIBÉRER N'ENVOIE L'ARGENT NULLE PART : on retire le nom sans en désigner un autre, la retenue reste où elle est et rien ne sera encaissé au profit de quelqu'un d'autre.
        if (blank($booking->employe_id)) {
            return;
        }

        // PREMIÈRE ATTRIBUTION : il n'y a personne à qui retirer quoi que ce soit.
        if (blank($booking->getOriginal('employe_id'))) {
            return;
        }

        // Rien n'est bloqué : la réassignation est libre.
        if ($booking->getOriginal('payment_status') !== 'authorized'
            || blank($booking->getOriginal('stripe_payment_intent_id'))) {
            return;
        }

        // La retenue vient d'être libérée dans la même écriture : c'est le chemin sanctionné.
        if ($booking->isDirty('stripe_payment_intent_id') && blank($booking->stripe_payment_intent_id)) {
            return;
        }

        throw ValidationException::withMessages([
            'employe_id' => ['Libérez d’abord la retenue bancaire : elle est bloquée au profit du professionnel précédent.'],
        ]);
    }
}

<?php

namespace App\Observers;

use App\Models\Booking;
use Illuminate\Validation\ValidationException;

/**
 * Empêche une réservation de changer de professionnel pendant qu'une somme est bloquée pour
 * l'ancien.
 *
 * L'autorisation Stripe est une « destination charge » : elle DÉSIGNE le compte du prestataire.
 * Changer d'intervenant sans toucher à la retenue enverrait l'argent chez quelqu'un qui n'a rien
 * fait, et le professionnel qui a réellement travaillé ne serait pas payé. Ce n'est pas un défaut
 * d'affichage, c'est de l'argent qui part au mauvais endroit.
 *
 * ON REFUSE PLUTÔT QUE DE CORRIGER EN SILENCE. Annuler la retenue depuis un observateur voudrait
 * dire appeler Stripe au milieu d'une sauvegarde : si l'appel échoue, la base est déjà modifiée et
 * la retenue tient encore. Le refus force le chemin explicite — `releaseForReassignment()` —, qui
 * libère d'abord et n'écrit qu'ensuite.
 */
class BookingPaymentDestinationObserver
{
    public function updating(Booking $booking): void
    {
        if (! $booking->isDirty('employe_id')) {
            return;
        }

        $previous = $booking->getOriginal('employe_id');

        // Première attribution : il n'y a personne à qui retirer quoi que ce soit.
        if (blank($previous)) {
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

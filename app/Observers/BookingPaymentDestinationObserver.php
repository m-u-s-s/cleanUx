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

        /*
         * LIBÉRER N'ENVOIE L'ARGENT NULLE PART : on retire le nom sans en désigner un autre, la
         * retenue reste où elle est et rien ne sera encaissé au profit de quelqu'un d'autre.
         */
        if (blank($booking->employe_id)) {
            return;
        }

        /*
         * PREMIÈRE ATTRIBUTION : il n'y a personne à qui retirer quoi que ce soit.
         *
         * Une réservation « autorisée » sans aucun prestataire ne devrait pas exister —
         * l'autorisation en exige un, `transfer_data.destination` étant posé à sa création. Mais
         * une ligne ancienne ou réparée à la main peut porter cet état, et refuser d'y attribuer
         * qui que ce soit la condamnerait définitivement.
         *
         * CETTE SORTIE N'EST SÛRE QUE PARCE QUE RIEN NE LA FABRIQUE. Le seul chemin qui retire un
         * nom sans en mettre un autre — le départ d'un salarié — laisse délibérément la réservation
         * intacte quand une retenue est active, faute de quoi on obtiendrait ici un contournement
         * en deux temps : libérer, puis attribuer sur une réservation devenue « vierge ». Voir
         * `OrganizationMemberAdministration::libererLesReservations()`.
         */
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

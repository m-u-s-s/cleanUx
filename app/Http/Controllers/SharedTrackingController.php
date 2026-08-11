<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\Client\SharedTrackingService;
use Illuminate\Contracts\View\View;

/**
 * LA PAGE PUBLIQUE DE SUIVI PARTAGÉ (E3).
 *
 * PUBLIQUE, MAIS PAS OUVERTE. La route porte le middleware `signed` : sans signature valide et non
 * périmée, Laravel répond 403 avant d'arriver ici. Un identifiant de réservation dans une URL
 * publique se devine en comptant ; un lien signé, non.
 *
 * AUCUNE AUTHENTIFICATION, ET C'EST TOUT L'INTÉRÊT. Le destinataire est la personne chez qui
 * l'intervention a lieu — souvent quelqu'un qui n'a pas de compte et n'en veut pas. Lui demander de
 * s'inscrire pour savoir à quelle heure sonner reviendrait à ne pas partager du tout.
 */
class SharedTrackingController extends Controller
{
    public function __invoke(Booking $booking): View
    {
        return view('tracking.shared', [
            // Volontairement pauvre : une position, une heure, un état. Ni montant, ni adresse
            // exacte, ni identité complète — le destinataire a besoin de savoir QUAND.
            'apercu' => app(SharedTrackingService::class)->apercu($booking),
        ]);
    }
}

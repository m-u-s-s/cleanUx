<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\Client\SharedTrackingService;
use Illuminate\Contracts\View\View;

/** LA PAGE PUBLIQUE DE SUIVI PARTAGÉ (E3). PUBLIQUE, MAIS PAS OUVERTE. */
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

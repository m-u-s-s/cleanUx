<?php

namespace App\Livewire\Client;

use App\Services\Client\HomeBudgetService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * LE BUDGET MAISON (E4).
 *
 * TOUT EST DÉJÀ EN BASE, et personne ne le voit. Un client reçoit ses factures une par une et n'a
 * aucun moyen de répondre à la seule question qu'il se pose : « combien est-ce que je dépense en
 * entretien, et est-ce que ça augmente ». C'est elle qui décide de passer à un abonnement, d'espacer
 * les interventions, ou de renoncer.
 *
 * LE COMPARATIF ABONNEMENT / À LA DEMANDE EST LE SEUL CHIFFRE QUI SERVE À DÉCIDER. Le reste
 * documente ; celui-ci répond — et c'est aussi le seul qui puisse convaincre quelqu'un de
 * s'abonner, en le montrant plutôt qu'en l'affirmant.
 */
class HomeBudget extends Component
{
    /** Fenêtre en mois. Douze par défaut : moins ne montre pas de tendance. */
    public int $mois = 12;

    public function render(): View
    {
        // Borné : une fenêtre venue du navigateur ne doit pas faire scanner dix ans de
        // réservations à chaque rendu.
        $mois = max(1, min(36, $this->mois));

        return view('livewire.client.home-budget', [
            'budget' => app(HomeBudgetService::class)->pour(
                Auth::user(),
                Carbon::now()->subMonths($mois)->startOfMonth(),
                Carbon::now()->endOfMonth(),
            ),
        ])->layout('layouts.app');
    }
}

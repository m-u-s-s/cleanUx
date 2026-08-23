<?php

namespace App\Livewire\Client;

use App\Services\Client\HomeBudgetService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** LE BUDGET MAISON (E4). TOUT EST DÉJÀ EN BASE, et personne ne le voit. */
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

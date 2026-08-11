<?php

namespace App\Livewire\Client;

use App\Services\Client\ProtectionOverviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * « MA PROTECTION » (E6).
 *
 * TOUTES LES BRIQUES EXISTENT : Insurance, Cancellation v2, Disputes. Chacune a son écran, sa
 * logique, ses tests. Et aucun client ne sait ce qu'il a. Il découvre son assurance au moment du
 * sinistre — trop tard pour la souscrire —, ses frais d'annulation en annulant, et l'existence des
 * litiges en cherchant un numéro de téléphone.
 *
 * CET ÉCRAN N'AJOUTE AUCUNE RÈGLE. Il lit les trois modules et les met côte à côte. C'est exactement
 * le point : une protection qu'on ne peut pas énoncer AVANT d'en avoir besoin n'en est pas une,
 * quelle que soit la qualité du moteur qui la calcule.
 */
class MyProtection extends Component
{
    public function render(): View
    {
        return view('livewire.client.my-protection', [
            'protection' => app(ProtectionOverviewService::class)->pour(Auth::user()),
        ])->layout('layouts.app');
    }
}

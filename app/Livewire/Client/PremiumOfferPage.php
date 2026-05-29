<?php

namespace App\Livewire\Client;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PremiumOfferPage extends Component
{
    public function isPremiumClient(): bool
    {
        return Auth::check() && Auth::user()->isPremium();
    }

    public function render(): View
    {
        return view('livewire.client.premium-offer-page', [
            'isPremium' => $this->isPremiumClient(),
            'premiumPrice' => 29,
        ]);
    }
}

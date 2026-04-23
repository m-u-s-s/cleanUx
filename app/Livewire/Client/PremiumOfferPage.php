<?php

namespace App\Livewire\Client;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;

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
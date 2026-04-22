<?php

namespace App\Livewire\Client;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PremiumOfferPage extends Component
{
    public function isPremiumClient(): bool
    {
        return Auth::check() && Auth::user()->isPremium();
    }

    public function render()
    {
        return view('livewire.client.premium-offer-page', [
            'isPremium' => $this->isPremiumClient(),
            'premiumPrice' => 29,
        ])->layout('layouts.guest');
    }
}
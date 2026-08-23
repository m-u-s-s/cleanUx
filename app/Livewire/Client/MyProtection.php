<?php

namespace App\Livewire\Client;

use App\Services\Client\ProtectionOverviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** « MA PROTECTION » (E6). TOUTES LES BRIQUES EXISTENT : Insurance, Cancellation v2, Disputes. */
class MyProtection extends Component
{
    public function render(): View
    {
        return view('livewire.client.my-protection', [
            'protection' => app(ProtectionOverviewService::class)->pour(Auth::user()),
        ])->layout('layouts.app');
    }
}

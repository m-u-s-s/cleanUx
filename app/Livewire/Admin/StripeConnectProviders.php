<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Livewire\Component;

class StripeConnectProviders extends Component
{
    public function render()
    {
        return view('livewire.admin.stripe-connect-providers', [
            'employees' => User::query()
                ->where('role', 'employe')
                ->orderBy('stripe_connect_status')
                ->orderBy('name')
                ->get(),
        ]);
    }
}
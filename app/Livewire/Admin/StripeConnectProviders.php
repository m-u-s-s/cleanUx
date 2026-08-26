<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Livewire\Component;

class StripeConnectProviders extends Component
{
    use EnforcesAdminAccess;

    public function render()
    {
        return view('livewire.admin.stripe-connect-providers', [
            'employees' => User::query()
                ->providers()
                ->orderBy('stripe_connect_status')
                ->orderBy('name')
                ->get(),
        ]);
    }
}

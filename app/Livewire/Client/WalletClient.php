<?php

namespace App\Livewire\Client;

use App\Models\CustomerCredit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class WalletClient extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public function getBalanceProperty(): float
    {
        return (float) CustomerCredit::query()
            ->where('client_id', Auth::id())
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->sum('remaining_amount');
    }

    public function render(): View
    {
        return view('livewire.client.wallet-client', [
            'credits' => CustomerCredit::query()
                ->with('rendezVous')
                ->where('client_id', Auth::id())
                ->latest()
                ->paginate(10),
            'balance' => $this->balance,
        ]);
    }
}
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

    public function getStatsProperty(): array
    {
        $credits = CustomerCredit::query()
            ->where('client_id', Auth::id())
            ->get();

        return [
            'active_count' => $credits->where('status', 'active')->count(),
            'used_count' => $credits->where('status', 'used')->count(),
            'expired_count' => $credits->where('status', 'expired')->count(),
            'total_received' => (float) $credits->sum('amount'),
            'total_used' => (float) $credits->sum(fn($credit) => max(0, $credit->amount - $credit->remaining_amount)),
        ];
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
            'stats' => $this->stats,
        ]);
    }
}

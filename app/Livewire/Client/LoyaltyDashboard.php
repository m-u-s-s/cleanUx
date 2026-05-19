<?php

namespace App\Livewire\Client;

use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTier;
use App\Models\LoyaltyTransaction;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class LoyaltyDashboard extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public function render(): View
    {
        $account = app(LoyaltyService::class)->accountFor(Auth::user());

        $currentTier = $account->currentTier;
        $allTiers = LoyaltyTier::query()->active()->ranked()->get();

        $nextTier = $allTiers
            ->filter(fn ($t) => $t->min_period_points > ($currentTier?->min_period_points ?? 0))
            ->sortBy('min_period_points')
            ->first();

        $progressPercent = 0;
        $pointsToNextTier = 0;
        if ($nextTier) {
            $currentMin = $currentTier?->min_period_points ?? 0;
            $delta = max(1, $nextTier->min_period_points - $currentMin);
            $progressPercent = min(100, (int) round((($account->period_points - $currentMin) / $delta) * 100));
            $pointsToNextTier = max(0, $nextTier->min_period_points - $account->period_points);
        }

        $transactions = LoyaltyTransaction::query()
            ->where('loyalty_account_id', $account->id)
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(15);

        return view('livewire.client.loyalty-dashboard', [
            'account' => $account,
            'currentTier' => $currentTier,
            'nextTier' => $nextTier,
            'allTiers' => $allTiers,
            'progressPercent' => $progressPercent,
            'pointsToNextTier' => $pointsToNextTier,
            'transactions' => $transactions,
        ]);
    }
}

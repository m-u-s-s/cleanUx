<?php

namespace App\Livewire\Admin\Loyalty;

use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTier;
use App\Models\LoyaltyTransaction;
use App\Models\User;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class LoyaltyCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $search = '';
    public ?int $filterTierId = null;

    public ?int $selectedUserId = null;
    public int $adjustPoints = 0;
    public string $adjustReason = '';

    public function selectUser(int $userId): void
    {
        $this->selectedUserId = $userId;
        $this->reset(['adjustPoints', 'adjustReason']);
    }

    public function closeDetail(): void
    {
        $this->reset(['selectedUserId', 'adjustPoints', 'adjustReason']);
    }

    public function adjust(): void
    {
        $this->validate([
            'adjustPoints' => ['required', 'integer', 'min:-100000', 'max:100000', 'not_in:0'],
            'adjustReason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $user = User::findOrFail($this->selectedUserId);

        app(LoyaltyService::class)->adminAdjust(
            $user,
            (int) $this->adjustPoints,
            Auth::user(),
            $this->adjustReason,
        );

        $this->reset(['adjustPoints', 'adjustReason']);
        $this->dispatch('toast', 'Ajustement appliqué.', 'success');
    }

    public function render(): View
    {
        $tiers = LoyaltyTier::query()->active()->ranked()->get();

        $kpis = [
            'total_members' => LoyaltyAccount::query()->count(),
            'active_30d' => LoyaltyAccount::query()
                ->where('last_activity_at', '>=', now()->subDays(30))->count(),
            'total_points_circulating' => (int) LoyaltyAccount::query()->sum('lifetime_points'),
        ];

        $distribution = $tiers->map(function (LoyaltyTier $tier) {
            return [
                'tier' => $tier,
                'count' => LoyaltyAccount::query()->where('current_tier_id', $tier->id)->count(),
            ];
        });

        $members = LoyaltyAccount::query()
            ->with(['user:id,name,email', 'currentTier'])
            ->when($this->filterTierId, fn ($q) => $q->where('current_tier_id', $this->filterTierId))
            ->when($this->search, function ($q) {
                $term = '%' . $this->search . '%';
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->orderByDesc('lifetime_points')
            ->paginate(15);

        $selected = $this->selectedUserId
            ? LoyaltyAccount::query()
                ->where('user_id', $this->selectedUserId)
                ->with(['user:id,name,email', 'currentTier'])
                ->first()
            : null;

        $selectedTransactions = $selected
            ? LoyaltyTransaction::query()
                ->where('loyalty_account_id', $selected->id)
                ->latest('occurred_at')
                ->limit(20)
                ->get()
            : collect();

        return view('livewire.admin.loyalty.loyalty-center', [
            'kpis' => $kpis,
            'tiers' => $tiers,
            'distribution' => $distribution,
            'members' => $members,
            'selected' => $selected,
            'selectedTransactions' => $selectedTransactions,
        ]);
    }
}

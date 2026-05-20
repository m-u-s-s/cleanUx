<?php

namespace App\Livewire\Client;

use App\Models\LoyaltyReward;
use App\Models\LoyaltyRedemption;
use App\Services\Loyalty\LoyaltyRedemptionService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class ClientLoyaltyRewards extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'catalogue';
    public string $typeFilter = '';
    public ?int $selectedRewardId = null;

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetPage();
    }

    public function openReward(int $rewardId): void
    {
        $this->selectedRewardId = $rewardId;
    }

    public function closeReward(): void
    {
        $this->selectedRewardId = null;
    }

    public function redeem(int $rewardId): void
    {
        $user = Auth::user();
        $reward = LoyaltyReward::findOrFail($rewardId);

        try {
            app(LoyaltyRedemptionService::class)->redeem($user, $reward);
            $this->selectedRewardId = null;
            $this->dispatch('toast', 'Récompense réservée — vérifiez vos emails.', 'success');
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first();
            $this->dispatch('toast', $firstError ?? 'Échec rédemption.', 'error');
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur : ' . $e->getMessage(), 'error');
        }
    }

    public function render(): View
    {
        $user = Auth::user();

        $balance = 0;
        $tierName = 'Bronze';
        if (Schema::hasTable('loyalty_accounts')) {
            $balance = (int) (DB::table('loyalty_accounts')
                ->where('user_id', $user->id)
                ->value('redeemable_points') ?? 0);
            if (Schema::hasTable('loyalty_tiers')) {
                $tier = DB::table('loyalty_accounts')
                    ->leftJoin('loyalty_tiers', 'loyalty_accounts.current_tier_id', '=', 'loyalty_tiers.id')
                    ->where('loyalty_accounts.user_id', $user->id)
                    ->value('loyalty_tiers.name');
                if ($tier) {
                    $tierName = $tier;
                }
            }
        }

        $rewards = LoyaltyReward::query()
            ->active()
            ->inStock()
            ->when($this->typeFilter, fn ($q) => $q->where('reward_type', $this->typeFilter))
            ->orderBy('points_cost')
            ->paginate(12, ['*'], 'rewardsPage');

        $myRedemptions = LoyaltyRedemption::query()
            ->where('user_id', $user->id)
            ->with('reward:id,name,reward_type,points_cost')
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'redemptionsPage');

        $selectedReward = $this->selectedRewardId
            ? LoyaltyReward::find($this->selectedRewardId)
            : null;

        return view('livewire.client.client-loyalty-rewards', [
            'balance' => $balance,
            'tierName' => $tierName,
            'rewards' => $rewards,
            'myRedemptions' => $myRedemptions,
            'selectedReward' => $selectedReward,
        ])->layout('layouts.app');
    }
}

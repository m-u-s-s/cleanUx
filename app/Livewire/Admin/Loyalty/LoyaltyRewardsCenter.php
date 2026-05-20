<?php

namespace App\Livewire\Admin\Loyalty;

use App\Models\LoyaltyRedemption;
use App\Models\LoyaltyReward;
use App\Services\Loyalty\LoyaltyRedemptionService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class LoyaltyRewardsCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'rewards';
    public string $search = '';
    public string $rewardTypeFilter = '';
    public string $statusFilter = '';

    public bool $showForm = false;
    public ?int $editRewardId = null;

    public string $form_code = '';
    public string $form_name = '';
    public string $form_description = '';
    public string $form_reward_type = LoyaltyReward::TYPE_DISCOUNT_CODE;
    public string $form_category = '';
    public int $form_points_cost = 100;
    public int $form_value_cents = 0;
    public string $form_currency = 'EUR';
    public int $form_min_tier_level = 0;
    public ?int $form_stock_initial = null;
    public bool $form_is_active = true;

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->reset(['editRewardId']);
        $this->form_code = 'rwd_' . substr(uniqid(), -8);
        $this->form_name = '';
        $this->form_description = '';
        $this->form_reward_type = LoyaltyReward::TYPE_DISCOUNT_CODE;
        $this->form_category = '';
        $this->form_points_cost = 100;
        $this->form_value_cents = 0;
        $this->form_currency = 'EUR';
        $this->form_min_tier_level = 0;
        $this->form_stock_initial = null;
        $this->form_is_active = true;
        $this->showForm = true;
    }

    public function openEdit(int $rewardId): void
    {
        $r = LoyaltyReward::findOrFail($rewardId);
        $this->editRewardId = $r->id;
        $this->form_code = $r->code;
        $this->form_name = $r->name;
        $this->form_description = $r->description ?? '';
        $this->form_reward_type = $r->reward_type;
        $this->form_category = $r->category ?? '';
        $this->form_points_cost = (int) $r->points_cost;
        $this->form_value_cents = (int) $r->value_cents;
        $this->form_currency = $r->currency ?? 'EUR';
        $this->form_min_tier_level = (int) $r->min_tier_level;
        $this->form_stock_initial = $r->stock_initial;
        $this->form_is_active = (bool) $r->is_active;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->reset(['showForm', 'editRewardId']);
    }

    public function save(): void
    {
        $this->validate([
            'form_code' => ['required', 'string', 'max:64'],
            'form_name' => ['required', 'string', 'max:255'],
            'form_reward_type' => ['required', 'in:discount_code,service_credit,physical_item,partner_voucher,charity_donation'],
            'form_points_cost' => ['required', 'integer', 'min:1', 'max:1000000'],
            'form_value_cents' => ['required', 'integer', 'min:0'],
            'form_currency' => ['required', 'string', 'size:3'],
            'form_min_tier_level' => ['required', 'integer', 'min:0', 'max:3'],
            'form_stock_initial' => ['nullable', 'integer', 'min:0'],
        ]);

        $payload = [
            'code' => $this->form_code,
            'name' => $this->form_name,
            'description' => $this->form_description ?: null,
            'reward_type' => $this->form_reward_type,
            'category' => $this->form_category ?: null,
            'points_cost' => $this->form_points_cost,
            'value_cents' => $this->form_value_cents,
            'currency' => $this->form_currency,
            'min_tier_level' => $this->form_min_tier_level,
            'stock_initial' => $this->form_stock_initial,
            'stock_remaining' => $this->form_stock_initial,
            'is_active' => $this->form_is_active,
        ];

        if ($this->editRewardId) {
            $reward = LoyaltyReward::findOrFail($this->editRewardId);
            // Préserver stock_remaining si édition (ne pas écraser stock déjà consommé)
            unset($payload['stock_remaining']);
            $reward->update($payload);
        } else {
            LoyaltyReward::create($payload);
        }

        $this->closeForm();
        $this->dispatch('toast', 'Récompense enregistrée.', 'success');
    }

    public function toggleActive(int $rewardId): void
    {
        $r = LoyaltyReward::findOrFail($rewardId);
        $r->update(['is_active' => ! $r->is_active]);
        $this->dispatch('toast', 'État mis à jour.', 'success');
    }

    public function cancelRedemption(int $redemptionId, string $reason = 'Annulation admin'): void
    {
        $redemption = LoyaltyRedemption::findOrFail($redemptionId);
        try {
            app(LoyaltyRedemptionService::class)->cancel($redemption, $reason);
            $this->dispatch('toast', 'Redemption annulée et points refundés.', 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur: ' . $e->getMessage(), 'error');
        }
    }

    public function markDelivered(int $redemptionId): void
    {
        $redemption = LoyaltyRedemption::findOrFail($redemptionId);
        app(LoyaltyRedemptionService::class)->markDelivered($redemption);
        $this->dispatch('toast', 'Marquée comme livrée.', 'success');
    }

    public function render(): View
    {
        $rewards = LoyaltyReward::query()
            ->when($this->search, function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(function ($w) use ($term) {
                    $w->where('name', 'like', $term)
                      ->orWhere('code', 'like', $term)
                      ->orWhere('category', 'like', $term);
                });
            })
            ->when($this->rewardTypeFilter, fn ($q) => $q->where('reward_type', $this->rewardTypeFilter))
            ->orderByDesc('created_at')
            ->paginate(15, ['*'], 'rewardsPage');

        $redemptions = LoyaltyRedemption::query()
            ->with(['user:id,name,email', 'reward:id,name,reward_type,points_cost'])
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('created_at')
            ->paginate(15, ['*'], 'redemptionsPage');

        $stats = [
            'rewards_total' => LoyaltyReward::query()->count(),
            'rewards_active' => LoyaltyReward::query()->where('is_active', true)->count(),
            'redemptions_total' => LoyaltyRedemption::query()->count(),
            'redemptions_pending' => LoyaltyRedemption::query()->where('status', LoyaltyRedemption::STATUS_PENDING)->count(),
            'redemptions_confirmed' => LoyaltyRedemption::query()->where('status', LoyaltyRedemption::STATUS_CONFIRMED)->count(),
            'points_spent_total' => (int) LoyaltyRedemption::query()
                ->whereIn('status', [
                    LoyaltyRedemption::STATUS_CONFIRMED,
                    LoyaltyRedemption::STATUS_DELIVERED,
                ])
                ->sum('points_spent'),
        ];

        return view('livewire.admin.loyalty.loyalty-rewards-center', [
            'rewards' => $rewards,
            'redemptions' => $redemptions,
            'stats' => $stats,
        ]);
    }
}

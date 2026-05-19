<?php

namespace App\Livewire\Admin\SubscriptionsV2;

use App\Models\SubscriptionsV2\SubscriptionCycleV2;
use App\Models\SubscriptionsV2\SubscriptionPlanV2;
use App\Models\SubscriptionsV2\SubscriptionV2;
use App\Services\SubscriptionsV2\BillingProcessor;
use App\Services\SubscriptionsV2\SubscriptionEngine;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class SubscriptionsCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'subscriptions';   // plans | subscriptions | cycles
    public string $filterStatus = '';
    public string $filterCycleStatus = '';

    public function retryBilling(int $cycleId): void
    {
        $cycle = SubscriptionCycleV2::findOrFail($cycleId);
        app(BillingProcessor::class)->processCycle($cycle);
        $this->dispatch('toast', 'Billing relancé.', 'success');
    }

    public function forceCancel(int $subId): void
    {
        $sub = SubscriptionV2::findOrFail($subId);
        app(SubscriptionEngine::class)->cancel($sub, true);
        $this->dispatch('toast', 'Subscription cancelled.', 'success');
    }

    public function render(): View
    {
        $kpis = [
            'plans_active' => SubscriptionPlanV2::query()->active()->count(),
            'subs_active' => SubscriptionV2::query()->active()->count(),
            'subs_past_due' => SubscriptionV2::query()->where('status', SubscriptionV2::STATUS_PAST_DUE)->count(),
            'cycles_failed' => SubscriptionCycleV2::query()->where('billing_status', SubscriptionCycleV2::STATUS_FAILED)->count(),
            'total_billed_cents' => (int) SubscriptionV2::query()->sum('total_billed_cents'),
        ];

        if ($this->tab === 'plans') {
            $items = SubscriptionPlanV2::query()
                ->orderBy('price_cents')
                ->paginate(20);
        } elseif ($this->tab === 'subscriptions') {
            $items = SubscriptionV2::query()
                ->with(['plan', 'user:id,email,name'])
                ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
                ->orderByDesc('created_at')
                ->paginate(25);
        } else {
            $items = SubscriptionCycleV2::query()
                ->with('subscription:id,code,user_id')
                ->when($this->filterCycleStatus, fn ($q) => $q->where('billing_status', $this->filterCycleStatus))
                ->orderByDesc('id')
                ->paginate(25);
        }

        return view('livewire.admin.subscriptions-v2.subscriptions-center', [
            'kpis' => $kpis,
            'items' => $items,
        ]);
    }
}

<?php

namespace App\Livewire\Client;

use App\Models\SubscriptionsV2\SubscriptionCycleV2;
use App\Models\SubscriptionsV2\SubscriptionPlanV2;
use App\Models\SubscriptionsV2\SubscriptionV2;
use App\Services\SubscriptionsV2\SubscriptionEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ClientSubscriptionsV2 extends Component
{
    public string $tab = 'mine';   // mine | plans | cycles
    public ?int $detailsForSubId = null;
    public string $confirmCancelCode = '';

    public function subscribe(string $planCode): void
    {
        $plan = SubscriptionPlanV2::query()->where('code', $planCode)->active()->first();
        if (! $plan) {
            $this->dispatch('toast', 'Plan introuvable.', 'error');
            return;
        }
        try {
            app(SubscriptionEngine::class)->subscribe(Auth::user(), $plan);
            $this->dispatch('toast', 'Abonnement créé.', 'success');
            $this->tab = 'mine';
        } catch (ValidationException $e) {
            $this->dispatch('toast', 'Erreur : ' . implode(' / ', collect($e->errors())->flatten()->all()), 'error');
        }
    }

    public function pause(int $subId): void
    {
        $sub = SubscriptionV2::query()->where('user_id', Auth::id())->findOrFail($subId);
        try {
            app(SubscriptionEngine::class)->pause($sub);
            $this->dispatch('toast', 'Abonnement en pause.', 'success');
        } catch (ValidationException $e) {
            $this->dispatch('toast', 'Erreur : ' . implode(' / ', collect($e->errors())->flatten()->all()), 'error');
        }
    }

    public function resume(int $subId): void
    {
        $sub = SubscriptionV2::query()->where('user_id', Auth::id())->findOrFail($subId);
        try {
            app(SubscriptionEngine::class)->resume($sub);
            $this->dispatch('toast', 'Abonnement réactivé.', 'success');
        } catch (ValidationException $e) {
            $this->dispatch('toast', 'Erreur : ' . implode(' / ', collect($e->errors())->flatten()->all()), 'error');
        }
    }

    public function cancelAtPeriodEnd(int $subId): void
    {
        $sub = SubscriptionV2::query()->where('user_id', Auth::id())->findOrFail($subId);
        app(SubscriptionEngine::class)->cancel($sub, immediate: false);
        $this->dispatch('toast', 'Abonnement annulé en fin de période.', 'success');
    }

    public function cancelImmediately(int $subId): void
    {
        $sub = SubscriptionV2::query()->where('user_id', Auth::id())->findOrFail($subId);
        // Confirmation simple côté front via wire:click + onclick="confirm()"
        app(SubscriptionEngine::class)->cancel($sub, immediate: true);
        $this->dispatch('toast', 'Abonnement annulé immédiatement.', 'success');
    }

    public function showDetails(int $subId): void
    {
        $sub = SubscriptionV2::query()->where('user_id', Auth::id())->find($subId);
        $this->detailsForSubId = $sub?->id;
    }

    #[Computed]
    public function mySubscriptions()
    {
        return SubscriptionV2::query()
            ->where('user_id', Auth::id())
            ->with('plan')
            ->orderByDesc('created_at')
            ->get();
    }

    #[Computed]
    public function plans()
    {
        return SubscriptionPlanV2::query()
            ->active()
            ->orderBy('price_cents')
            ->get();
    }

    public function render(): View
    {
        $cycles = collect();
        if ($this->detailsForSubId) {
            $cycles = SubscriptionCycleV2::query()
                ->whereHas('subscription', function ($q) {
                    $q->where('user_id', Auth::id());
                })
                ->where('subscription_id', $this->detailsForSubId)
                ->orderByDesc('cycle_number')
                ->limit(24)
                ->get();
        }

        return view('livewire.client.client-subscriptions-v2', [
            'cycles' => $cycles,
        ]);
    }
}

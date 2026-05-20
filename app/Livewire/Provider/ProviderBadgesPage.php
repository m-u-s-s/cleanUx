<?php

namespace App\Livewire\Provider;

use App\Models\ProviderBadge;
use App\Models\ProviderBadgeAward;
use App\Services\Badges\ProviderBadgeEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ProviderBadgesPage extends Component
{
    public function refresh(): void
    {
        $user = Auth::user();
        $awarded = app(ProviderBadgeEngine::class)->evaluate($user);
        if (! empty($awarded)) {
            $this->dispatch('toast', count($awarded) . ' nouveau(x) badge(s) débloqué(s) !', 'success');
        } else {
            $this->dispatch('toast', 'Aucun nouveau badge pour le moment.', 'info');
        }
    }

    public function render(): View
    {
        $user = Auth::user();

        $earnedBadgeIds = ProviderBadgeAward::query()
            ->where('provider_user_id', $user->id)
            ->pluck('badge_id')
            ->toArray();

        $allBadges = ProviderBadge::query()
            ->where('is_active', true)
            ->orderBy('criterion_type')
            ->orderBy('threshold')
            ->get();

        $earnedAwards = ProviderBadgeAward::query()
            ->where('provider_user_id', $user->id)
            ->with('badge')
            ->orderByDesc('awarded_at')
            ->get();

        return view('livewire.provider.provider-badges-page', [
            'allBadges' => $allBadges,
            'earnedBadgeIds' => $earnedBadgeIds,
            'earnedAwards' => $earnedAwards,
        ])->layout('layouts.app');
    }
}

<?php

namespace App\Livewire\Admin\ApiTokensV2;

use App\Models\ApiTokenScope;
use App\Models\ApiTokenUsage;
use App\Models\Sanctum\PersonalAccessTokenV2;
use App\Services\ApiTokensV2\ApiTokenManager;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class ApiTokensCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'tokens';   // tokens | scopes | usages

    public function suspend(int $tokenId, string $reason = 'Suspendu via admin UI (Centre Tokens)'): void
    {
        $token = PersonalAccessTokenV2::findOrFail($tokenId);
        try {
            app(ApiTokenManager::class)->suspend($token, $reason);
            $this->dispatch('toast', 'Token suspendu.', 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur : ' . $e->getMessage(), 'error');
        }
    }

    public function unsuspend(int $tokenId): void
    {
        $token = PersonalAccessTokenV2::findOrFail($tokenId);
        app(ApiTokenManager::class)->unsuspend($token);
        $this->dispatch('toast', 'Token réactivé.', 'success');
    }

    public function revoke(int $tokenId): void
    {
        $token = PersonalAccessTokenV2::findOrFail($tokenId);
        app(ApiTokenManager::class)->revoke($token);
        $this->dispatch('toast', 'Token révoqué.', 'success');
    }

    public function render(): View
    {
        $kpis = [
            'tokens_total' => PersonalAccessTokenV2::query()->count(),
            'tokens_active' => PersonalAccessTokenV2::query()->active()->count(),
            'tokens_suspended' => PersonalAccessTokenV2::query()->whereNotNull('suspended_at')->count(),
            'usages_24h' => ApiTokenUsage::query()->where('occurred_at', '>=', now()->subDay())->count(),
        ];

        if ($this->tab === 'tokens') {
            $items = PersonalAccessTokenV2::query()
                ->orderByDesc('created_at')
                ->paginate(20);
        } elseif ($this->tab === 'scopes') {
            $items = ApiTokenScope::query()
                ->orderBy('category')
                ->orderBy('code')
                ->paginate(50);
        } else {
            $items = ApiTokenUsage::query()
                ->orderByDesc('occurred_at')
                ->paginate(25);
        }

        return view('livewire.admin.api-tokens-v2.api-tokens-center', [
            'kpis' => $kpis,
            'items' => $items,
        ]);
    }
}

<?php

namespace App\Livewire\Admin\Bundles;

use App\Models\MultiTradeBundle;
use App\Models\MultiTradeBundleItem;
use App\Models\User;
use App\Services\Bundles\MultiTradeBundleService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class BundlesCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $statusFilter = '';
    public string $search = '';
    public ?int $selectedBundleId = null;
    public ?int $assigningItemId = null;
    public ?int $assignProviderId = null;
    public int $assignQuotedEur = 0;

    public function openBundle(int $id): void
    {
        $this->selectedBundleId = $id;
    }

    public function closeBundle(): void
    {
        $this->selectedBundleId = null;
        $this->assigningItemId = null;
    }

    public function openAssign(int $itemId): void
    {
        $this->assigningItemId = $itemId;
        $this->assignProviderId = null;
        $this->assignQuotedEur = 0;
    }

    public function assignProvider(): void
    {
        $item = MultiTradeBundleItem::find($this->assigningItemId);
        $provider = User::find($this->assignProviderId);
        if (! $item || ! $provider) {
            return;
        }
        try {
            app(MultiTradeBundleService::class)->quoteItem(
                $item,
                $provider,
                (int) round($this->assignQuotedEur * 100),
            );
            $this->dispatch('toast', 'Provider assigné + quote enregistrée.', 'success');
            $this->assigningItemId = null;
        } catch (\Throwable $e) {
            $this->dispatch('toast', $e->getMessage(), 'error');
        }
    }

    public function forceCancel(int $bundleId): void
    {
        $bundle = MultiTradeBundle::find($bundleId);
        if (! $bundle) return;
        app(MultiTradeBundleService::class)->cancel($bundle, 'admin_force_cancel');
        $this->dispatch('toast', 'Bundle annulé.', 'success');
        $this->closeBundle();
    }

    public function render(): View
    {
        $bundles = MultiTradeBundle::query()
            ->with(['client:id,name,email', 'items.trade:id,name', 'items.provider:id,name'])
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(function ($w) use ($term) {
                    $w->where('code', 'like', $term)
                      ->orWhere('name', 'like', $term)
                      ->orWhereHas('client', fn ($u) => $u->where('email', 'like', $term)->orWhere('name', 'like', $term));
                });
            })
            ->orderByDesc('created_at')
            ->paginate(15);

        $stats = [
            'total' => MultiTradeBundle::query()->count(),
            'draft' => MultiTradeBundle::query()->where('status', 'draft')->count(),
            'quoting' => MultiTradeBundle::query()->where('status', 'quoting')->count(),
            'quoted' => MultiTradeBundle::query()->where('status', 'quoted')->count(),
            'in_progress' => MultiTradeBundle::query()->where('status', 'in_progress')->count(),
            'total_value_cents' => (int) MultiTradeBundle::query()->whereIn('status', ['accepted', 'in_progress', 'completed'])->sum('total_quoted_cents'),
        ];

        $selectedBundle = $this->selectedBundleId
            ? MultiTradeBundle::query()
                ->where('id', $this->selectedBundleId)
                ->with(['client', 'items.trade', 'items.provider'])
                ->first()
            : null;

        // Providers list (light) for assignment
        $availableProviders = [];
        if ($this->assigningItemId) {
            $availableProviders = User::query()
                ->where('role', 'employe')
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(50)
                ->get(['id', 'name', 'email']);
        }

        return view('livewire.admin.bundles.bundles-center', [
            'bundles' => $bundles,
            'stats' => $stats,
            'selectedBundle' => $selectedBundle,
            'availableProviders' => $availableProviders,
        ]);
    }
}

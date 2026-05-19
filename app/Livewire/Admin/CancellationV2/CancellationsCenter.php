<?php

namespace App\Livewire\Admin\CancellationV2;

use App\Models\BookingCancellationV2;
use App\Models\CancellationPolicy;
use App\Services\CancellationV2\CancellationEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class CancellationsCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'recent';  // recent | overrides | policies
    public string $filterActorRole = '';
    public string $search = '';

    public function override(int $cancellationId, string $reason = ''): void
    {
        $row = BookingCancellationV2::findOrFail($cancellationId);
        try {
            app(CancellationEngine::class)->override($row, Auth::user(), $reason ?: 'Override via admin UI: ' . now()->toIso8601String());
            $this->dispatch('toast', 'Cancellation overridden — fee waived.', 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur : ' . $e->getMessage(), 'error');
        }
    }

    public function render(): View
    {
        $kpis = [
            'cancellations_7d' => BookingCancellationV2::query()
                ->where('cancelled_at', '>=', now()->subDays(7))->count(),
            'fees_collected_7d_cents' => (int) BookingCancellationV2::query()
                ->where('cancelled_at', '>=', now()->subDays(7))
                ->sum('fee_amount_cents'),
            'overrides_7d' => BookingCancellationV2::query()
                ->whereNotNull('override_admin_user_id')
                ->where('cancelled_at', '>=', now()->subDays(7))->count(),
            'active_policies' => CancellationPolicy::query()->active()->count(),
        ];

        if ($this->tab === 'recent') {
            $items = BookingCancellationV2::query()
                ->with(['policy:id,code,name', 'cancelledBy:id,email,name'])
                ->when($this->filterActorRole, fn ($q) => $q->where('actor_role', $this->filterActorRole))
                ->when($this->search, function ($q) {
                    $term = '%' . $this->search . '%';
                    $q->whereHas('cancelledBy', fn ($u) => $u->where('email', 'like', $term));
                })
                ->orderByDesc('cancelled_at')
                ->paginate(25);
        } elseif ($this->tab === 'overrides') {
            $items = BookingCancellationV2::query()
                ->with(['policy:id,code', 'overriddenBy:id,email'])
                ->whereNotNull('override_admin_user_id')
                ->orderByDesc('cancelled_at')
                ->paginate(25);
        } else {
            $items = CancellationPolicy::query()
                ->withCount('tiers')
                ->orderBy('code')
                ->paginate(25);
        }

        return view('livewire.admin.cancellation-v2.cancellations-center', [
            'kpis' => $kpis,
            'items' => $items,
        ]);
    }
}

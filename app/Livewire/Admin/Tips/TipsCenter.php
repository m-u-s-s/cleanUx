<?php

namespace App\Livewire\Admin\Tips;

use App\Models\BookingTip;
use App\Services\Tips\TipService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class TipsCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $statusFilter = '';
    public string $search = '';

    public function confirmTip(int $tipId): void
    {
        $tip = BookingTip::findOrFail($tipId);
        try {
            app(TipService::class)->confirmCharge($tip, 'manual_admin_' . $tipId);
            $this->dispatch('toast', 'Tip marqué chargé.', 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', $e->getMessage(), 'error');
        }
    }

    public function markPaidOut(int $tipId): void
    {
        $tip = BookingTip::findOrFail($tipId);
        try {
            app(TipService::class)->markPaidOut($tip, 'manual_payout_' . $tipId);
            $this->dispatch('toast', 'Payout enregistré.', 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', $e->getMessage(), 'error');
        }
    }

    public function markFailed(int $tipId): void
    {
        $tip = BookingTip::findOrFail($tipId);
        app(TipService::class)->markFailed($tip, 'admin_manual_fail');
        $this->dispatch('toast', 'Marqué failed.', 'success');
    }

    public function render(): View
    {
        $tips = BookingTip::query()
            ->with(['client:id,name,email', 'provider:id,name', 'booking:id'])
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(function ($w) use ($term) {
                    $w->where('code', 'like', $term)
                      ->orWhereHas('client', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term))
                      ->orWhereHas('provider', fn ($u) => $u->where('name', 'like', $term));
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        $stats = [
            'total_count' => BookingTip::query()->count(),
            'pending_count' => BookingTip::query()->where('status', BookingTip::STATUS_PENDING)->count(),
            'charged_count' => BookingTip::query()->where('status', BookingTip::STATUS_CHARGED)->count(),
            'paid_out_count' => BookingTip::query()->where('status', BookingTip::STATUS_PAID_OUT)->count(),
            'total_charged_cents' => (int) BookingTip::query()->charged()->sum('amount_cents'),
            'avg_tip_cents' => (int) BookingTip::query()->charged()->avg('amount_cents'),
        ];

        return view('livewire.admin.tips.tips-center', [
            'tips' => $tips,
            'stats' => $stats,
        ]);
    }
}

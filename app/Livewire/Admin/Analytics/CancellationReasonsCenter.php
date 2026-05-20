<?php

namespace App\Livewire\Admin\Analytics;

use App\Models\Booking;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Admin analytics : pivot des raisons d'annulation pour identifier les frictions.
 * Source : bookings.cancellation_reason + cancelled_at.
 */
class CancellationReasonsCenter extends Component
{
    public string $period = '30d';
    public string $groupBy = 'reason';

    public function setPeriod(string $period): void
    {
        $this->period = $period;
    }

    public function setGroupBy(string $g): void
    {
        $this->groupBy = $g;
    }

    public function render(): View
    {
        $since = match ($this->period) {
            '7d' => Carbon::now()->subDays(7),
            '30d' => Carbon::now()->subDays(30),
            '90d' => Carbon::now()->subDays(90),
            'all' => Carbon::create(2020, 1, 1),
            default => Carbon::now()->subDays(30),
        };

        $base = Booking::query()
            ->whereIn('status', ['annule', 'cancelled', 'canceled'])
            ->where(function ($q) use ($since) {
                $q->where('cancelled_at', '>=', $since)
                  ->orWhere('updated_at', '>=', $since);
            });

        $totalCancelled = (clone $base)->count();
        $totalAll = Booking::query()
            ->where(function ($q) use ($since) {
                $q->where('created_at', '>=', $since)
                  ->orWhere('updated_at', '>=', $since);
            })
            ->count();

        $cancellationRate = $totalAll > 0
            ? round(($totalCancelled / $totalAll) * 100, 2)
            : 0;

        $rows = (clone $base)
            ->selectRaw('cancellation_reason, COUNT(*) as count, SUM(COALESCE(cancellation_fee_amount,0)) as total_fee_cents')
            ->whereNotNull('cancellation_reason')
            ->where('cancellation_reason', '!=', '')
            ->groupBy('cancellation_reason')
            ->orderByDesc('count')
            ->limit(30)
            ->get();

        $byCancelledBy = (clone $base)
            ->selectRaw('cancelled_by, COUNT(*) as count')
            ->whereNotNull('cancelled_by')
            ->groupBy('cancelled_by')
            ->get();

        return view('livewire.admin.analytics.cancellation-reasons-center', [
            'totalCancelled' => $totalCancelled,
            'totalAll' => $totalAll,
            'cancellationRate' => $cancellationRate,
            'rows' => $rows,
            'byCancelledBy' => $byCancelledBy,
        ]);
    }
}

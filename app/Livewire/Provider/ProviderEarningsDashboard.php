<?php

namespace App\Livewire\Provider;

use App\Models\Booking;
use App\Models\BookingTip;
use App\Models\ProviderWalletTransaction;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

/**
 * Dashboard provider earnings (revenus jour/semaine/mois + tips + projections).
 *
 * Lit depuis :
 *   - bookings (status=termine, amount captured)
 *   - booking_tips (status=charged/paid_out)
 *   - provider_wallet_transactions (ledger immuable provider)
 *
 * Toutes les agrégations sont per-period (today / this_week / this_month + prev period).
 */
class ProviderEarningsDashboard extends Component
{
    public string $period = 'week';   // today | week | month | year

    public function setPeriod(string $period): void
    {
        $this->period = $period;
    }

    public function render(): View
    {
        $user = Auth::user();
        [$start, $end, $prevStart, $prevEnd, $bucketFormat] = $this->periodRanges();

        $current = $this->aggregate($user->id, $start, $end);
        $previous = $this->aggregate($user->id, $prevStart, $prevEnd);

        $delta = $this->deltaPercent($current['gross_cents'], $previous['gross_cents']);
        $missionsDelta = $this->deltaPercent($current['missions_count'], $previous['missions_count']);

        // Timeseries pour graph
        $series = $this->timeseries($user->id, $start, $end, $bucketFormat);

        // Top métiers
        $topTrades = $this->topTrades($user->id, $start, $end);

        return view('livewire.provider.provider-earnings-dashboard', [
            'period' => $this->period,
            'current' => $current,
            'previous' => $previous,
            'delta' => $delta,
            'missionsDelta' => $missionsDelta,
            'series' => $series,
            'topTrades' => $topTrades,
        ])->layout('layouts.app');
    }

    protected function periodRanges(): array
    {
        $now = Carbon::now();
        return match ($this->period) {
            'today' => [
                $now->copy()->startOfDay(),
                $now->copy()->endOfDay(),
                $now->copy()->subDay()->startOfDay(),
                $now->copy()->subDay()->endOfDay(),
                'H',
            ],
            'week' => [
                $now->copy()->startOfWeek(),
                $now->copy()->endOfWeek(),
                $now->copy()->subWeek()->startOfWeek(),
                $now->copy()->subWeek()->endOfWeek(),
                'D',
            ],
            'month' => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
                'd/m',
            ],
            'year' => [
                $now->copy()->startOfYear(),
                $now->copy()->endOfYear(),
                $now->copy()->subYear()->startOfYear(),
                $now->copy()->subYear()->endOfYear(),
                'M',
            ],
            default => [
                $now->copy()->startOfWeek(),
                $now->copy()->endOfWeek(),
                $now->copy()->subWeek()->startOfWeek(),
                $now->copy()->subWeek()->endOfWeek(),
                'D',
            ],
        };
    }

    protected function aggregate(int $userId, Carbon $start, Carbon $end): array
    {
        $missionsQuery = Booking::query()
            ->where(function ($q) use ($userId) {
                $q->where('employe_id', $userId)
                  ->orWhere('assigned_employee_id', $userId);
            })
            ->whereIn('status', ['termine', 'completed', 'closed'])
            ->whereBetween('updated_at', [$start, $end]);

        $missionsCount = (clone $missionsQuery)->count();
        $grossCentsFromBookings = (int) (clone $missionsQuery)
            ->sum(DB::raw('COALESCE(provider_amount_cents, payment_amount_cents, ROUND(devis_estime * 100))'));

        $tipsCents = 0;
        if (Schema::hasTable('booking_tips')) {
            $tipsCents = (int) BookingTip::query()
                ->where('provider_user_id', $userId)
                ->whereIn('status', [BookingTip::STATUS_CHARGED, BookingTip::STATUS_PAID_OUT])
                ->whereBetween('created_at', [$start, $end])
                ->sum('amount_cents');
        }

        $walletEarnedCents = 0;
        $walletPaidOutCents = 0;
        if (Schema::hasTable('provider_wallet_transactions')) {
            $base = ProviderWalletTransaction::query()
                ->where('user_id', $userId)
                ->whereBetween('created_at', [$start, $end]);
            $walletEarnedCents = (int) (clone $base)->where('direction', 'credit')->sum('amount_cents');
            $walletPaidOutCents = (int) (clone $base)->where('type', 'payout')->sum('amount_cents');
        }

        return [
            'missions_count' => $missionsCount,
            'gross_cents' => $grossCentsFromBookings + $tipsCents,
            'mission_cents' => $grossCentsFromBookings,
            'tips_cents' => $tipsCents,
            'wallet_credited_cents' => $walletEarnedCents,
            'wallet_paid_out_cents' => $walletPaidOutCents,
        ];
    }

    protected function timeseries(int $userId, Carbon $start, Carbon $end, string $bucketFormat): array
    {
        // Simple bucketization en PHP — pour scale, basculer sur SQL GROUP BY DATE_FORMAT
        $bookings = Booking::query()
            ->where(function ($q) use ($userId) {
                $q->where('employe_id', $userId)
                  ->orWhere('assigned_employee_id', $userId);
            })
            ->whereIn('status', ['termine', 'completed', 'closed'])
            ->whereBetween('updated_at', [$start, $end])
            ->get(['updated_at', 'provider_amount_cents', 'payment_amount_cents', 'devis_estime']);

        $buckets = [];
        foreach ($bookings as $b) {
            $key = $b->updated_at->format($bucketFormat);
            $cents = (int) ($b->provider_amount_cents ?? $b->payment_amount_cents ?? round(((float) $b->devis_estime) * 100));
            $buckets[$key] = ($buckets[$key] ?? 0) + $cents;
        }

        return array_map(fn ($key, $cents) => [
            'label' => $key,
            'amount_cents' => $cents,
            'amount_eur' => round($cents / 100, 2),
        ], array_keys($buckets), array_values($buckets));
    }

    protected function topTrades(int $userId, Carbon $start, Carbon $end): array
    {
        if (! Schema::hasColumn('bookings', 'trade_id')) {
            return [];
        }
        $rows = Booking::query()
            ->where(function ($q) use ($userId) {
                $q->where('employe_id', $userId)
                  ->orWhere('assigned_employee_id', $userId);
            })
            ->whereIn('status', ['termine', 'completed', 'closed'])
            ->whereBetween('updated_at', [$start, $end])
            ->whereNotNull('trade_id')
            ->select('trade_id', DB::raw('COUNT(*) as missions'), DB::raw('SUM(COALESCE(provider_amount_cents, payment_amount_cents, ROUND(devis_estime * 100))) as total_cents'))
            ->groupBy('trade_id')
            ->orderByDesc('total_cents')
            ->limit(5)
            ->get();

        return $rows->map(function ($r) {
            $trade = \App\Models\Trade::find($r->trade_id);
            return [
                'trade_name' => $trade?->name ?? 'Trade #' . $r->trade_id,
                'missions' => (int) $r->missions,
                'total_eur' => round(((int) $r->total_cents) / 100, 2),
            ];
        })->toArray();
    }

    protected function deltaPercent(int|float $current, int|float $previous): ?float
    {
        if ($previous === 0 || $previous === 0.0) {
            return $current > 0 ? 100.0 : null;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }
}

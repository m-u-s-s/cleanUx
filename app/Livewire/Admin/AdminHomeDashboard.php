<?php

namespace App\Livewire\Admin;

use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Models\Mission;
use App\Models\ProviderPayout;
use App\Models\ProviderPresence;
use App\Models\WebhookDelivery;
use Carbon\Carbon;
use Livewire\Component;

class AdminHomeDashboard extends Component
{
    public function render()
    {
        $today = now()->startOfDay();

        return view('livewire.admin.admin-home-dashboard', [
            'bookingsToday' => Booking::whereDate('created_at', $today)->count(),
            'activeMissions' => Mission::whereIn('status', ['planned', 'en_route', 'started'])->count(),
            'providersOnline' => ProviderPresence::where('status', 'online')->count(),
            'revenueToday' => $this->revenueToday($today),
            'pendingPayouts' => ProviderPayout::where('status', ProviderPayout::STATUS_PENDING)->count(),
            'webhookFailures24h' => WebhookDelivery::whereIn('status', [
                WebhookDelivery::STATUS_FAILED,
                WebhookDelivery::STATUS_DEAD,
            ])->where('updated_at', '>=', now()->subHours(24))->count(),
            'recentDisputes' => ComplaintCase::whereIn('status', ['open', 'assigned', 'investigating'])
                ->latest()
                ->take(5)
                ->get(),
            'recentBookings' => Booking::with('serviceCatalog:id,name')
                ->latest()
                ->take(10)
                ->get(['id', 'booking_reference', 'status', 'service_catalog_id', 'created_at', 'scheduled_date']),
            'bookingsTrend' => $this->bookingsTrend(),
        ]);
    }

    /**
     * Returns 7-day booking counts for the trend sparkline chart.
     *
     * @return array<int, array{date: string, count: int}>
     */
    public function bookingsTrend(): array
    {
        // M21 — one grouped query for the 7-day window instead of 7 separate whereDate counts.
        $start = now()->subDays(6)->startOfDay();

        $counts = Booking::query()
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as d, count(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        return collect(range(6, 0))->map(function (int $daysAgo) use ($counts) {
            $date = now()->subDays($daysAgo);

            return [
                'date' => $date->format('d/m'),
                'count' => (int) ($counts[$date->format('Y-m-d')] ?? 0),
            ];
        })->all();
    }

    private function revenueToday(Carbon $today): float
    {
        return (float) Booking::whereDate('created_at', $today)
            ->whereNotNull('estimated_price')
            ->whereIn('status', ['confirme', 'completed', 'termine', 'sur_place', 'on_site'])
            ->sum('estimated_price');
    }
}

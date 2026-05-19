<?php

namespace App\Livewire\Admin\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\AnalyticsSession;
use App\Services\Analytics\AnalyticsFunnel;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AnalyticsCenter extends Component
{
    public string $rangeKey = '7d';  // 24h | 7d | 30d

    public string $funnelType = 'booking';

    public function render(): View
    {
        [$from, $to] = $this->range();

        $kpis = [
            'events' => AnalyticsEvent::query()->between($from, $to)->count(),
            'unique_users' => AnalyticsEvent::query()
                ->between($from, $to)
                ->whereNotNull('user_id')
                ->distinct('user_id')
                ->count('user_id'),
            'sessions' => AnalyticsSession::query()
                ->where('started_at', '>=', $from)
                ->where('started_at', '<', $to)
                ->count(),
            'revenue_cents' => (int) AnalyticsEvent::query()
                ->between($from, $to)
                ->whereNotNull('revenue_cents')
                ->sum('revenue_cents'),
        ];

        $topEvents = AnalyticsEvent::query()
            ->between($from, $to)
            ->selectRaw('event_name, COUNT(*) as total')
            ->groupBy('event_name')
            ->orderByDesc('total')
            ->limit(15)
            ->get();

        $funnel = $this->buildFunnel($from, $to);

        return view('livewire.admin.analytics.analytics-center', [
            'kpis' => $kpis,
            'topEvents' => $topEvents,
            'funnel' => $funnel,
            'from' => $from,
            'to' => $to,
        ]);
    }

    protected function range(): array
    {
        $to = now();
        $from = match ($this->rangeKey) {
            '24h' => now()->subDay(),
            '30d' => now()->subDays(30),
            default => now()->subDays(7),
        };
        return [$from, $to];
    }

    protected function buildFunnel(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $steps = match ($this->funnelType) {
            'booking' => [
                'search.performed',
                'provider.viewed',
                'booking.started',
                'booking.created',
                'booking.confirmed',
                'booking.completed',
            ],
            'registration' => [
                'page.viewed',
                'user.registered',
                'booking.created',
            ],
            default => [],
        };

        return AnalyticsFunnel::for($from, $to)
            ->steps($steps)
            ->groupBy('user_id')
            ->compute();
    }
}

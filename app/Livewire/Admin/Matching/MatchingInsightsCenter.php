<?php

namespace App\Livewire\Admin\Matching;

use App\Models\Booking;
use App\Models\BookingMatchingDecision;
use App\Models\ProviderPerformanceMetric;
use App\Services\Matching\MatchingV2Service;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Config;
use Livewire\Component;
use Livewire\WithPagination;

class MatchingInsightsCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'recent_decisions';

    public ?int $simulateBookingId = null;
    public ?array $simulationResult = null;

    public function simulate(): void
    {
        $this->simulationResult = null;

        if (! $this->simulateBookingId) {
            $this->addError('simulateBookingId', 'ID de booking requis.');
            return;
        }

        $booking = Booking::find($this->simulateBookingId);
        if (! $booking) {
            $this->addError('simulateBookingId', 'Booking introuvable.');
            return;
        }

        try {
            $ranked = app(MatchingV2Service::class)->topN($booking, 10);
            $this->simulationResult = [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'service_zone_id' => $booking->service_zone_id,
                'date' => $booking->date,
                'candidates' => $ranked->map(fn ($r) => [
                    'user_id' => $r['employee']->id,
                    'name' => $r['employee']->name,
                    'score' => $r['score'],
                    'components' => $r['breakdown']->components,
                    'context' => $r['breakdown']->context,
                ])->all(),
            ];
        } catch (\Throwable $e) {
            $this->addError('simulateBookingId', 'Erreur simulation: ' . $e->getMessage());
        }
    }

    public function render(): View
    {
        $kpis = [
            'decisions_total' => BookingMatchingDecision::query()->count(),
            'decisions_today' => BookingMatchingDecision::query()
                ->whereDate('created_at', today())->count(),
            'avg_top_score' => round((float) BookingMatchingDecision::query()
                ->whereNotNull('top_score')->avg('top_score'), 2),
            'avg_candidates' => round((float) BookingMatchingDecision::query()
                ->avg('candidates_count'), 1),
        ];

        if ($this->tab === 'recent_decisions') {
            $items = BookingMatchingDecision::query()
                ->with(['booking:id,booking_reference,date,heure', 'selectedProvider:id,name'])
                ->latest()
                ->paginate(15);
            $view = 'decisions';
        } elseif ($this->tab === 'provider_metrics') {
            $items = ProviderPerformanceMetric::query()
                ->with(['provider:id,name'])
                ->latest('period_end')
                ->paginate(15);
            $view = 'metrics';
        } else {
            $items = collect();
            $view = 'simulator';
        }

        return view('livewire.admin.matching.matching-insights-center', [
            'kpis' => $kpis,
            'weights' => Config::get('matching.weights', []),
            'config' => [
                'enabled' => (bool) Config::get('matching.enabled', true),
                'version' => Config::get('matching.version', 'v2'),
                'shadow_mode' => (bool) Config::get('matching.shadow_mode', false),
                'min_score' => Config::get('matching.thresholds.min_acceptable_score', 30),
            ],
            'items' => $items,
            'currentView' => $view,
        ]);
    }
}

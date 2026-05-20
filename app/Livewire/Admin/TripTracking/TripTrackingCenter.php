<?php

namespace App\Livewire\Admin\TripTracking;

use App\Models\TripTrackingPoint;
use App\Models\TripTrackingSession;
use App\Services\TripTracking\TripTrackingService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class TripTrackingCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'live';
    public string $statusFilter = '';
    public string $search = '';
    public ?int $selectedSessionId = null;

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetPage();
    }

    public function openSession(int $sessionId): void
    {
        $this->selectedSessionId = $sessionId;
    }

    public function closeDetail(): void
    {
        $this->selectedSessionId = null;
    }

    public function cancelSession(int $sessionId): void
    {
        $session = TripTrackingSession::findOrFail($sessionId);
        app(TripTrackingService::class)->cancelSession($session, 'admin_manual');
        $this->dispatch('toast', 'Session annulée.', 'success');
    }

    public function render(): View
    {
        $liveSessions = TripTrackingSession::query()
            ->active()
            ->with(['provider:id,name', 'booking:id,client_id'])
            ->orderByDesc('last_ping_at')
            ->limit(50)
            ->get();

        $historySessions = TripTrackingSession::query()
            ->whereIn('status', [TripTrackingSession::STATUS_ENDED, TripTrackingSession::STATUS_CANCELLED])
            ->with(['provider:id,name', 'booking:id,client_id'])
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(function ($w) use ($term) {
                    $w->where('code', 'like', $term)
                      ->orWhereHas('provider', fn ($u) => $u->where('name', 'like', $term));
                });
            })
            ->orderByDesc('ended_at')
            ->paginate(20);

        $selectedSession = null;
        $selectedPoints = collect();
        if ($this->selectedSessionId) {
            $selectedSession = TripTrackingSession::query()
                ->with(['provider:id,name', 'booking:id'])
                ->find($this->selectedSessionId);
            if ($selectedSession) {
                $selectedPoints = TripTrackingPoint::query()
                    ->where('session_id', $selectedSession->id)
                    ->orderBy('recorded_at')
                    ->limit(500)
                    ->get(['lat', 'lng', 'eta_seconds', 'distance_to_dest_m', 'speed_mps', 'recorded_at']);
            }
        }

        $stats = [
            'active_now' => $liveSessions->count(),
            'enroute' => $liveSessions->where('status', TripTrackingSession::STATUS_ENROUTE)->count(),
            'arrived' => $liveSessions->where('status', TripTrackingSession::STATUS_ARRIVED)->count(),
            'in_mission' => $liveSessions->where('status', TripTrackingSession::STATUS_IN_MISSION)->count(),
            'total_history' => TripTrackingSession::query()
                ->whereIn('status', [TripTrackingSession::STATUS_ENDED, TripTrackingSession::STATUS_CANCELLED])
                ->count(),
        ];

        return view('livewire.admin.trip-tracking.trip-tracking-center', [
            'liveSessions' => $liveSessions,
            'historySessions' => $historySessions,
            'selectedSession' => $selectedSession,
            'selectedPoints' => $selectedPoints,
            'stats' => $stats,
        ]);
    }
}

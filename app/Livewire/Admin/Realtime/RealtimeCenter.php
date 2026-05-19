<?php

namespace App\Livewire\Admin\Realtime;

use App\Models\BroadcastEvent;
use App\Realtime\RealtimeBroadcastService;
use App\Support\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class RealtimeCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $filterCategory = '';
    public string $filterAudience = '';
    public string $filterStatus = '';
    public string $search = '';

    public function replay(int $broadcastEventId): void
    {
        $event = BroadcastEvent::findOrFail($broadcastEventId);

        $ok = app(RealtimeBroadcastService::class)->replay($event);

        ActivityLogger::log('realtime.manual_replay', $event, [
            'admin_user_id' => Auth::id(),
            'success' => $ok,
        ]);

        $this->dispatch('toast',
            $ok ? 'Broadcast rejoué avec succès.' : 'Échec du replay : ' . $event->failed_reason,
            $ok ? 'success' : 'error',
        );
    }

    public function render(): View
    {
        $kpis = [
            'total_24h' => BroadcastEvent::query()
                ->where('queued_at', '>=', now()->subDay())->count(),
            'sent_24h' => BroadcastEvent::query()
                ->where('status', BroadcastEvent::STATUS_SENT)
                ->where('queued_at', '>=', now()->subDay())->count(),
            'failed_24h' => BroadcastEvent::query()
                ->where('status', BroadcastEvent::STATUS_FAILED)
                ->where('queued_at', '>=', now()->subDay())->count(),
            'distinct_channels_24h' => BroadcastEvent::query()
                ->where('queued_at', '>=', now()->subDay())
                ->distinct('channel')->count('channel'),
        ];

        $items = BroadcastEvent::query()
            ->when($this->filterCategory, fn ($q) => $q->where('category', $this->filterCategory))
            ->when($this->filterAudience, fn ($q) => $q->where('audience', $this->filterAudience))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->search, function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('channel', 'like', $term)
                        ->orWhere('event_class', 'like', $term)
                        ->orWhere('broadcast_as', 'like', $term);
                });
            })
            ->latest('queued_at')
            ->paginate(25);

        return view('livewire.admin.realtime.realtime-center', [
            'kpis' => $kpis,
            'items' => $items,
        ]);
    }
}

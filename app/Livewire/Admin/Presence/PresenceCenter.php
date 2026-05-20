<?php

namespace App\Livewire\Admin\Presence;

use App\Models\ProviderPresence;
use App\Services\Presence\ProviderPresenceService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class PresenceCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $statusFilter = '';
    public string $search = '';

    public function forceOffline(int $userId): void
    {
        $user = \App\Models\User::find($userId);
        if (! $user) {
            return;
        }
        app(ProviderPresenceService::class)->goOffline($user);
        $this->dispatch('toast', 'Provider mis offline.', 'success');
    }

    public function scanStale(): void
    {
        $count = app(ProviderPresenceService::class)->scanStale(5);
        $this->dispatch('toast', "{$count} provider(s) auto-offline.", 'success');
    }

    public function render(): View
    {
        $now = now();
        $cutoff = $now->copy()->subMinutes(5);

        $rows = ProviderPresence::query()
            ->with('provider:id,name,email')
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, function ($q) {
                $term = '%' . $this->search . '%';
                $q->whereHas('provider', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->orderByDesc('heartbeat_at')
            ->paginate(25);

        $stats = [
            'online' => ProviderPresence::query()->where('status', ProviderPresence::STATUS_ONLINE)->count(),
            'busy' => ProviderPresence::query()->where('status', ProviderPresence::STATUS_BUSY)->count(),
            'on_break' => ProviderPresence::query()->where('status', ProviderPresence::STATUS_ON_BREAK)->count(),
            'offline' => ProviderPresence::query()->where('status', ProviderPresence::STATUS_OFFLINE)->count(),
            'stale_candidates' => ProviderPresence::query()
                ->whereIn('status', [ProviderPresence::STATUS_ONLINE, ProviderPresence::STATUS_BUSY])
                ->where('heartbeat_at', '<', $cutoff)
                ->count(),
            'total_online_minutes_today' => (int) ProviderPresence::query()->sum('online_minutes_today'),
        ];

        return view('livewire.admin.presence.presence-center', [
            'rows' => $rows,
            'stats' => $stats,
        ]);
    }
}

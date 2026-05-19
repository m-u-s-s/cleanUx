<?php

namespace App\Livewire\Admin\Audit;

use App\Models\AuditEvent;
use App\Services\Audit\AuditService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class AuditCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $filterDomain = '';
    public string $filterSeverity = '';
    public string $filterEventType = '';
    public string $search = '';
    public bool $pinnedOnly = false;

    public function togglePin(int $eventId): void
    {
        $event = AuditEvent::findOrFail($eventId);
        if ($event->is_pinned) {
            app(AuditService::class)->unpin($event);
            $this->dispatch('toast', 'Event unpinned.', 'success');
        } else {
            app(AuditService::class)->pin($event);
            $this->dispatch('toast', 'Event pinned (won\'t be purged).', 'success');
        }
    }

    public function render(): View
    {
        $kpis = [
            'events_24h' => AuditEvent::query()->where('occurred_at', '>=', now()->subDay())->count(),
            'critical_24h' => AuditEvent::query()
                ->where('severity', AuditEvent::SEVERITY_CRITICAL)
                ->where('occurred_at', '>=', now()->subDay())->count(),
            'errors_24h' => AuditEvent::query()
                ->where('severity', AuditEvent::SEVERITY_ERROR)
                ->where('occurred_at', '>=', now()->subDay())->count(),
            'pinned_total' => AuditEvent::query()->where('is_pinned', true)->count(),
        ];

        $items = AuditEvent::query()
            ->when($this->filterDomain, fn ($q) => $q->where('domain', $this->filterDomain))
            ->when($this->filterSeverity, fn ($q) => $q->where('severity', $this->filterSeverity))
            ->when($this->filterEventType, fn ($q) => $q->where('event_type', 'like', '%' . $this->filterEventType . '%'))
            ->when($this->search, function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('actor_label', 'like', $term)
                        ->orWhere('subject_label', 'like', $term)
                        ->orWhere('event_type', 'like', $term);
                });
            })
            ->when($this->pinnedOnly, fn ($q) => $q->where('is_pinned', true))
            ->orderByDesc('occurred_at')
            ->paginate(25);

        return view('livewire.admin.audit.audit-center', [
            'kpis' => $kpis,
            'items' => $items,
        ]);
    }
}

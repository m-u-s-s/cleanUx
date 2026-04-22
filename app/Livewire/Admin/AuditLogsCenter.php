<?php

namespace App\Livewire\Admin;

use App\Models\ActivityLog;
use App\Models\ServiceZone;
use Livewire\Component;
use Livewire\WithPagination;

class AuditLogsCenter extends Component
{
    use WithPagination;

    public string $search = '';
    public string $actionFilter = '';
    public string $domainFilter = '';
    public string $actorFilter = '';
    public string $severityFilter = '';
    public string $zoneFilter = '';
    public bool $criticalOnly = false;
    public int $perPage = 15;

    protected $paginationTheme = 'tailwind';

    public function mount(): void
    {
        if (auth()->user()?->isZoneScopedAdmin()) {
            $this->zoneFilter = (string) auth()->user()->managed_service_zone_id;
        }
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'actionFilter', 'domainFilter', 'actorFilter', 'severityFilter', 'zoneFilter', 'criticalOnly', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function getAvailableActionsProperty()
    {
        return ActivityLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');
    }

    public function getDomainsProperty()
    {
        return ActivityLog::query()
            ->select('domain')
            ->whereNotNull('domain')
            ->distinct()
            ->orderBy('domain')
            ->pluck('domain');
    }

    public function getZonesProperty()
    {
        return ServiceZone::query()->orderBy('name')->get(['id', 'name']);
    }

    public function getLogsProperty()
    {
        $user = auth()->user();
        $criticalKeywords = ['delete', 'supprime', 'suspend', 'export', 'finance', 'incident', 'quality', 'premium', 'user', 'security'];

        return ActivityLog::query()
            ->with(['user', 'serviceZone'])
            ->when($user?->isZoneScopedAdmin(), function ($query) use ($user) {
                $query->where(function ($scoped) use ($user) {
                    $scoped->where('service_zone_id', $user->managed_service_zone_id)
                        ->orWhere(function ($selfOnly) use ($user) {
                            $selfOnly->whereNull('service_zone_id')
                                ->where('user_id', $user->id);
                        });
                });
            })
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('action', 'like', '%' . $this->search . '%')
                        ->orWhere('target_type', 'like', '%' . $this->search . '%')
                        ->orWhere('target_id', 'like', '%' . $this->search . '%')
                        ->orWhere('request_id', 'like', '%' . $this->search . '%')
                        ->orWhereHas('user', function ($userQuery) {
                            $userQuery->where('name', 'like', '%' . $this->search . '%')
                                ->orWhere('email', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->when($this->actionFilter !== '', fn ($query) => $query->where('action', $this->actionFilter))
            ->when($this->domainFilter !== '', fn ($query) => $query->where('domain', $this->domainFilter))
            ->when($this->severityFilter !== '', fn ($query) => $query->where('severity', $this->severityFilter))
            ->when($this->zoneFilter !== '', fn ($query) => $query->where('service_zone_id', (int) $this->zoneFilter))
            ->when($this->actorFilter === 'system', fn ($query) => $query->whereNull('user_id'))
            ->when($this->actorFilter === 'human', fn ($query) => $query->whereNotNull('user_id'))
            ->when($this->criticalOnly, function ($query) use ($criticalKeywords) {
                $query->where(function ($q) use ($criticalKeywords) {
                    $q->where('is_critical', true);

                    foreach ($criticalKeywords as $keyword) {
                        $q->orWhere('action', 'like', '%' . $keyword . '%');
                    }
                });
            })
            ->latest()
            ->paginate($this->perPage);
    }

    public function render()
    {
        return view('livewire.admin.audit-logs-center', [
            'logs' => $this->logs,
            'availableActions' => $this->availableActions,
            'domains' => $this->domains,
            'zones' => $this->zones,
            'severities' => collect(['info', 'warning', 'error']),
        ]);
    }
}

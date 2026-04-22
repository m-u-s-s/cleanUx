<?php

namespace App\Livewire\Admin;

use App\Models\ActivityLog;
use Livewire\Component;
use Livewire\WithPagination;

class LogsActivity extends Component
{
    use WithPagination;

    public string $search = '';
    public string $actionFilter = '';
    public int $perPage = 10;

    protected $paginationTheme = 'tailwind';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingActionFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function getAvailableActionsProperty()
    {
        return ActivityLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');
    }

    public function getLogsProperty()
    {
        return ActivityLog::query()
            ->with('user')
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('action', 'like', '%' . $this->search . '%')
                        ->orWhere('target_type', 'like', '%' . $this->search . '%')
                        ->orWhere('target_id', 'like', '%' . $this->search . '%')
                        ->orWhereHas('user', function ($userQuery) {
                            $userQuery->where('name', 'like', '%' . $this->search . '%')
                                ->orWhere('email', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->when($this->actionFilter !== '', function ($query) {
                $query->where('action', $this->actionFilter);
            })
            ->latest()
            ->paginate($this->perPage);
    }

    public function render()
    {
        return view('livewire.admin.logs-activity', [
            'logs' => $this->logs,
            'availableActions' => $this->availableActions,
        ]);
    }
}
<?php

namespace App\Livewire\ProviderCompany;

use App\Models\OrganizationMember;
use App\Models\Task;
use App\Services\PermissionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TaskBoard extends Component
{
    // ──────────────────────────────────────────────────────
    // State
    // ──────────────────────────────────────────────────────
    public bool    $showCreate   = false;
    public ?int    $editingId    = null;
    public string  $filterMember = '';
    public string  $filterPrio   = '';

    // Formulaire
    public string  $title        = '';
    public string  $description  = '';
    public string  $priority     = Task::PRIORITY_MEDIUM;
    public string  $dueDate      = '';
    public array   $assigneeIds  = [];

    // ──────────────────────────────────────────────────────
    // Mount
    // ──────────────────────────────────────────────────────
    public function mount(): void
    {
        $user = Auth::user();
        abort_unless(
            app(PermissionService::class)->can($user, 'tasks.create', $user->currentOrganization),
            403
        );
    }

    // ──────────────────────────────────────────────────────
    // Computed
    // ──────────────────────────────────────────────────────
    public function getTodoTasksProperty()
    {
        return $this->queryTasks()->todo()->get();
    }

    public function getInProgressTasksProperty()
    {
        return $this->queryTasks()->inProgress()->get();
    }

    public function getDoneTasksProperty()
    {
        return $this->queryTasks()->done()->limit(20)->get();
    }

    public function getMembersProperty()
    {
        $orgId = Auth::user()->current_organization_id;

        return OrganizationMember::where('organization_account_id', $orgId)
            ->where('status', 'active')
            ->with('user:id,name,profile_photo_path')
            ->get();
    }

    // ──────────────────────────────────────────────────────
    // CRUD
    // ──────────────────────────────────────────────────────
    public function createTask(): void
    {
        $this->validate([
            'title'       => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'priority'    => ['required', 'in:low,medium,high,urgent'],
            'dueDate'     => ['nullable', 'date'],
            'assigneeIds' => ['array'],
        ]);

        $user  = Auth::user();
        $orgId = $user->current_organization_id;

        $task = Task::create([
            'organization_account_id' => $orgId,
            'created_by'              => $user->id,
            'title'                   => $this->title,
            'description'             => $this->description,
            'priority'                => $this->priority,
            'status'                  => Task::STATUS_TODO,
            'due_date'                => $this->dueDate ?: null,
        ]);

        // Assigner les membres
        if (!empty($this->assigneeIds)) {
            $task->assignees()->attach($this->assigneeIds, [
                'assigned_by' => $user->id,
                'assigned_at' => now(),
            ]);
        }

        $this->resetForm();
        $this->showCreate = false;
    }

    public function updateStatus(int $taskId, string $newStatus): void
    {
        $task = Task::forOrg(Auth::user()->current_organization_id)->find($taskId);

        if (! $task) {
            return;
        }

        $updates = ['status' => $newStatus];

        if ($newStatus === Task::STATUS_DONE) {
            $updates['completed_at'] = now();
        }

        $task->update($updates);
    }

    public function deleteTask(int $taskId): void
    {
        $user = Auth::user();
        $task = Task::forOrg($user->current_organization_id)->find($taskId);

        if (! $task) {
            return;
        }

        // Seul le créateur ou un manager peut supprimer
        $canDelete = $task->created_by === $user->id
            || app(PermissionService::class)->can($user, 'tasks.close', $user->currentOrganization);

        if (! $canDelete) {
            return;
        }

        $task->delete();
    }

    // ──────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────
    private function queryTasks()
    {
        $query = Task::forOrg(Auth::user()->current_organization_id)
            ->with(['assignees:id,name,profile_photo_path', 'creator:id,name'])
            ->latest('updated_at');

        if ($this->filterMember) {
            $query->assignedTo((int) $this->filterMember);
        }

        if ($this->filterPrio) {
            $query->where('priority', $this->filterPrio);
        }

        return $query;
    }

    private function resetForm(): void
    {
        $this->title       = '';
        $this->description = '';
        $this->priority    = Task::PRIORITY_MEDIUM;
        $this->dueDate     = '';
        $this->assigneeIds = [];
        $this->editingId   = null;
    }

    // ──────────────────────────────────────────────────────
    // Render
    // ──────────────────────────────────────────────────────
    public function render()
    {
        return view('livewire.provider-company.task-board', [
            'todoTasks'       => $this->todoTasksProperty,
            'inProgressTasks' => $this->inProgressTasksProperty,
            'doneTasks'       => $this->doneTasksProperty,
            'members'         => $this->membersProperty,
        ])->layout('layouts.provider-company');
    }
}

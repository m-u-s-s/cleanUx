<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Livewire\ProviderCompany\TaskBoard;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** NOTE (infra-blocked branches): Task::assignees() declares withPivot(['assigned_by','assigned_at']) but the task_assignees migration never creates those columns. */
class TaskBoardCoverageBatch15Test extends TestCase
{
    use RefreshDatabase;

    /**
     * Build an org + an active member with the given role.
     *
     * @return array{org: OrganizationAccount, user: User}
     */
    private function makeMember(string $role = OrganizationRole::OWNER->value): array
    {
        $org = OrganizationAccount::factory()->create();
        $user = User::factory()->create(['current_organization_id' => $org->id]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
        ]);

        return compact('org', 'user');
    }

    // ──────────────────────────────────────────────────────
    // mount()
    // ──────────────────────────────────────────────────────

    public function test_mount_aborts_403_without_tasks_create_permission(): void
    {
        $ctx = $this->makeMember(OrganizationRole::VIEWER->value);

        Livewire::actingAs($ctx['user'])
            ->test(TaskBoard::class)
            ->assertStatus(403);
    }

    public function test_mount_renders_empty_board_for_owner(): void
    {
        $ctx = $this->makeMember();

        Livewire::actingAs($ctx['user'])
            ->test(TaskBoard::class)
            ->assertOk()
            ->assertSet('showCreate', false)
            ->assertSet('priority', Task::PRIORITY_MEDIUM);
    }

    public function test_mount_lists_active_members(): void
    {
        // A second active member should surface through getMembersProperty.
        $ctx = $this->makeMember();
        $colleague = User::factory()->create(['name' => 'Colette Collègue']);
        OrganizationMember::create([
            'organization_account_id' => $ctx['org']->id,
            'user_id' => $colleague->id,
            'role' => OrganizationRole::WORKER->value,
            'status' => 'active',
        ]);

        Livewire::actingAs($ctx['user'])
            ->test(TaskBoard::class)
            ->assertOk()
            ->assertSee('Colette Collègue');
    }

    // ──────────────────────────────────────────────────────
    // createTask() validation
    // ──────────────────────────────────────────────────────

    public function test_create_task_validates_required_title(): void
    {
        $ctx = $this->makeMember();

        Livewire::actingAs($ctx['user'])
            ->test(TaskBoard::class)
            ->set('title', '')
            ->call('createTask')
            ->assertHasErrors(['title' => 'required']);

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_create_task_validates_priority(): void
    {
        $ctx = $this->makeMember();

        Livewire::actingAs($ctx['user'])
            ->test(TaskBoard::class)
            ->set('title', 'Titre valide')
            ->set('priority', 'bogus')
            ->call('createTask')
            ->assertHasErrors(['priority']);

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_create_task_validates_due_date_format(): void
    {
        $ctx = $this->makeMember();

        Livewire::actingAs($ctx['user'])
            ->test(TaskBoard::class)
            ->set('title', 'Titre valide')
            ->set('dueDate', 'pas-une-date')
            ->call('createTask')
            ->assertHasErrors(['dueDate']);

        $this->assertDatabaseCount('tasks', 0);
    }

    // ──────────────────────────────────────────────────────
    // queryTasks() filter branches (empty board)
    // ──────────────────────────────────────────────────────

    public function test_filters_apply_without_error_on_empty_board(): void
    {
        $ctx = $this->makeMember();

        Livewire::actingAs($ctx['user'])
            ->test(TaskBoard::class)
            ->set('filterMember', (string) $ctx['user']->id)
            ->set('filterPrio', Task::PRIORITY_HIGH)
            ->assertOk();
    }

    // ──────────────────────────────────────────────────────
    // updateStatus() guards
    // ──────────────────────────────────────────────────────

    public function test_update_status_ignores_missing_task(): void
    {
        $ctx = $this->makeMember();

        Livewire::actingAs($ctx['user'])
            ->test(TaskBoard::class)
            ->call('updateStatus', 999999, Task::STATUS_DONE)
            ->assertOk();
    }

    public function test_update_status_ignores_task_from_other_org(): void
    {
        // Task lives in a different org: forOrg(current) finds nothing, so the
        // status must remain untouched and the board (still empty) renders fine.
        $ctx = $this->makeMember();
        $otherOrg = OrganizationAccount::factory()->create();
        $task = Task::create([
            'organization_account_id' => $otherOrg->id,
            'created_by' => $ctx['user']->id,
            'title' => 'Tâche autre org',
            'status' => Task::STATUS_TODO,
            'priority' => Task::PRIORITY_MEDIUM,
        ]);

        Livewire::actingAs($ctx['user'])
            ->test(TaskBoard::class)
            ->call('updateStatus', $task->id, Task::STATUS_DONE);

        $this->assertSame(Task::STATUS_TODO, $task->fresh()->status);
    }

    // ──────────────────────────────────────────────────────
    // deleteTask() guard
    // ──────────────────────────────────────────────────────

    public function test_delete_task_ignores_missing_task(): void
    {
        $ctx = $this->makeMember();

        Livewire::actingAs($ctx['user'])
            ->test(TaskBoard::class)
            ->call('deleteTask', 999999)
            ->assertOk();
    }
}

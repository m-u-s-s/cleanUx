<?php

namespace App\Livewire\ProviderCompany;

use App\Models\OrganizationMember;
use App\Models\Task;
use App\Services\PermissionService;
use App\Services\Tasks\TaskVisibilityService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * @property-read Collection<int, Task> $todoTasks
 * @property-read Collection<int, Task> $inProgressTasks
 * @property-read Collection<int, Task> $doneTasks
 * @property-read Collection<int, OrganizationMember> $members
 */
class TaskBoard extends Component
{
    use EnforcesActiveOrgMembership;

    // ──────────────────────────────────────────────────────
    // State
    // ──────────────────────────────────────────────────────
    public bool $showCreate = false;

    public ?int $editingId = null;

    public string $filterMember = '';

    public string $filterPrio = '';

    // Formulaire
    public string $title = '';

    public string $description = '';

    public string $priority = Task::PRIORITY_MEDIUM;

    public string $dueDate = '';

    public array $assigneeIds = [];

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

    /**
     * Les membres à qui l'on peut confier une tâche.
     *
     * CETTE LISTE EST UN TROMBINOSCOPE, et le tableau des tâches est ouvert à toute la maison —
     * `tasks.create` est accordée jusqu'au nettoyeur. Elle ne sert qu'à deux choses, le sélecteur
     * d'assignation et le filtre par personne, qui supposent l'une comme l'autre de distribuer le
     * travail. Sans `tasks.assign`, la rendre reviendrait à publier l'effectif de la société sur le
     * seul écran que le lot 1 laisse ouvert.
     */
    public function getMembersProperty()
    {
        $user = Auth::user();

        if (! app(PermissionService::class)->can($user, 'tasks.assign', $user->currentOrganization)) {
            return OrganizationMember::query()->whereRaw('1 = 0')->get();
        }

        return OrganizationMember::where('organization_account_id', $user->current_organization_id)
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
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'dueDate' => ['nullable', 'date'],
            'assigneeIds' => ['array'],
        ]);

        $user = Auth::user();
        $orgId = $user->current_organization_id;

        /*
         * LES ACTIONS N'ÉTAIENT GARDÉES QU'AU MONTAGE (corrigé le 2026-08-05).
         *
         * `mount()` vérifie une permission de lecture, puis cette méthode n'en vérifiait plus
         * aucune : un `viewer` — rôle en lecture seule — créait des tâches. La clé `tasks.create`
         * existait dans la matrice de permissions sans qu'aucun appelant ne la consulte.
         */
        abort_unless(
            app(PermissionService::class)->can($user, 'tasks.create', $user->currentOrganization),
            403
        );

        $task = Task::create([
            'organization_account_id' => $orgId,
            'created_by' => $user->id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => Task::STATUS_TODO,
            'due_date' => $this->dueDate ?: null,
        ]);

        /*
         * LES IDENTIFIANTS D'ASSIGNATION VIENNENT DU NAVIGATEUR (corrigé le 2026-08-05).
         *
         * `$assigneeIds` était attaché tel quel : on pouvait assigner une tâche à un utilisateur
         * d'une AUTRE société. On ne retient donc que les membres actifs de l'organisation, et on
         * exige `tasks.assign` — clé, elle aussi, déclarée mais jamais consultée.
         */
        if (! empty($this->assigneeIds)) {
            abort_unless(
                app(PermissionService::class)->can($user, 'tasks.assign', $user->currentOrganization),
                403
            );

            $membresLegitimes = OrganizationMember::query()
                ->where('organization_account_id', $orgId)
                ->where('status', 'active')
                ->whereIn('user_id', $this->assigneeIds)
                ->pluck('user_id')
                ->all();

            if ($membresLegitimes !== []) {
                $task->assignees()->attach($membresLegitimes, [
                    'assigned_by' => $user->id,
                    'assigned_at' => now(),
                ]);
            }
        }

        $this->resetForm();
        $this->showCreate = false;
    }

    public function updateStatus(int $taskId, string $newStatus): void
    {
        $user = Auth::user();
        $task = Task::forOrg($user->current_organization_id)->find($taskId);

        if (! $task) {
            return;
        }

        /*
         * Déplacer une tâche est une écriture : un rôle en lecture seule ne doit pas pouvoir
         * marquer terminé le travail des autres. `tasks.create` gouverne la participation au
         * tableau ; le créateur de la tâche garde la main sur la sienne.
         */
        abort_unless(
            $task->created_by === $user->id
                || app(PermissionService::class)->can($user, 'tasks.create', $user->currentOrganization),
            403
        );

        if (! $this->estVisible($taskId)) {
            return;
        }

        // La table tasks n'a pas de colonne done-timestamp (completed_at/done_at/...) :
        // le passage du status à "done" est la source de vérité. updated_at suffit.
        $task->update(['status' => $newStatus]);
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

        if (! $canDelete || ! $this->estVisible($taskId)) {
            return;
        }

        $task->delete();
    }

    // ──────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────

    /**
     * Cette tâche figure-t-elle sur le tableau de l'appelant ?
     *
     * L'IDENTIFIANT VIENT DU NAVIGATEUR. Les deux écritures ne bornaient la tâche qu'à
     * l'organisation, puis la gardaient par `tasks.create` — clé accordée jusqu'au nettoyeur : en
     * devinant un identifiant, chacun marquait « terminée » la tâche d'un collègue qu'il n'avait
     * même pas le droit de lire.
     *
     * LA VÉRIFICATION VIENT APRÈS LA PERMISSION, ET L'ORDRE COMPTE. Charger d'emblée par la requête
     * de visibilité rendait silencieux un refus qui doit rester bruyant : `TaskBoardActionGuardsTest`
     * fige le 403 obtenu quand un droit est retiré pendant qu'un onglet reste ouvert. Les deux
     * questions sont distinctes — « avez-vous le droit d'écrire ici » se répond par 403, « cette
     * tâche est-elle la vôtre » par un silence, qui n'apprend rien sur son existence.
     */
    private function estVisible(int $taskId): bool
    {
        return app(TaskVisibilityService::class)
            ->requetePour(Auth::user(), Auth::user()->current_organization_id)
            ->whereKey($taskId)
            ->exists();
    }

    /**
     * LE TABLEAU MONTRAIT TOUTES LES TÂCHES DE LA SOCIÉTÉ À QUI POUVAIT L'OUVRIR.
     *
     * Le seul filtre était l'organisation. La règle « le tableau entier pour qui distribue le
     * travail, mes tâches pour qui l'exécute » vit désormais dans `TaskVisibilityService`, partagé
     * avec l'API mobile : deux filtres écrits séparément auraient dérivé.
     */
    private function queryTasks()
    {
        $query = app(TaskVisibilityService::class)
            ->requetePour(Auth::user(), Auth::user()->current_organization_id)
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
        $this->title = '';
        $this->description = '';
        $this->priority = Task::PRIORITY_MEDIUM;
        $this->dueDate = '';
        $this->assigneeIds = [];
        $this->editingId = null;
    }

    // ──────────────────────────────────────────────────────
    // Render
    // ──────────────────────────────────────────────────────
    public function render()
    {
        return view('livewire.provider-company.task-board', [
            'todoTasks' => $this->todoTasks,
            'inProgressTasks' => $this->inProgressTasks,
            'doneTasks' => $this->doneTasks,
            'members' => $this->members,
        ])->layout('layouts.provider-company');
    }
}

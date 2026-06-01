<?php

namespace App\Livewire\ProviderCompany;

use App\Events\MissionStatusUpdated;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\OrganizationMember;
use App\Services\PermissionService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @property-read Collection<int, Mission> $missions
 * @property-read Collection<int, OrganizationMember> $availableWorkers
 */
class DispatchCenter extends Component
{
    public string $filterDate = '';

    public string $filterStatus = '';

    public int $assigningId = 0;

    public ?int $assigneeId = null;

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless(
            app(PermissionService::class)->can($user, 'missions.dispatch', $user->currentOrganization),
            403
        );

        $this->filterDate = now()->format('Y-m-d');
    }

    public function getMissionsProperty()
    {
        $orgId = Auth::user()->current_organization_id;

        return Mission::where('provider_organization_id', $orgId)
            ->when($this->filterDate, fn ($q) => $q->whereDate('planned_start_at', $this->filterDate)
            )
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus)
            )
            ->with([
                'assignments.provider:id,name,profile_photo_path',
                'bookingSite:id,name,address,city,lat,lng',
            ])
            ->orderBy('planned_start_at')
            ->get();
    }

    public function getAvailableWorkersProperty()
    {
        $orgId = Auth::user()->current_organization_id;

        return OrganizationMember::where('organization_account_id', $orgId)
            ->whereIn('role', ['worker', 'team_lead'])
            ->where('status', 'active')
            ->with('user:id,name,profile_photo_path')
            ->get();
    }

    public function startAssign(int $missionId): void
    {
        $this->assigningId = $missionId;
        $this->assigneeId = null;
    }

    public function confirmAssign(): void
    {
        if (! $this->assigningId || ! $this->assigneeId) {
            return;
        }

        $user = Auth::user();
        $mission = Mission::where('provider_organization_id', $user->current_organization_id)
            ->findOrFail($this->assigningId);

        $worker = OrganizationMember::where('organization_account_id', $user->current_organization_id)
            ->where('user_id', $this->assigneeId)
            ->firstOrFail();

        // Créer l'assignment (colonnes réelles de mission_assignments :
        // user_id, role_on_mission, assignment_status, assigned_at —
        // pas de provider_user_id ni assigned_by ; cf. MissionAssignmentStatusService).
        MissionAssignment::updateOrCreate(
            ['mission_id' => $mission->id, 'user_id' => $worker->user_id],
            ['role_on_mission' => 'lead', 'assignment_status' => 'assigned', 'assigned_at' => now()]
        );

        $mission->update(['status' => 'assigned']);

        // Broadcast du changement de statut
        broadcast(new MissionStatusUpdated($mission));

        $this->assigningId = 0;
        $this->assigneeId = null;
    }

    public function cancelAssign(): void
    {
        $this->assigningId = 0;
        $this->assigneeId = null;
    }

    #[On('echo-private:mission.{assigningId},MissionStatusUpdated')]
    public function onMissionUpdate(): void
    {
        // Rafraîchit automatiquement la vue quand une mission change
    }

    public function render()
    {
        return view('livewire.provider-company.dispatch-center', [
            'missions' => $this->missions,
            'availableWorkers' => $this->availableWorkers,
        ])->layout('layouts.provider-company');
    }
}

<?php

namespace App\Livewire\ProviderCompany;

use App\Events\MissionStatusUpdated;
use App\Models\Mission;
use App\Models\OrganizationContract;
use App\Models\OrganizationMember;
use App\Services\Missions\MissionAssignmentService;
use App\Services\PermissionService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @property-read Collection<int, Mission> $missions
 * @property-read Collection<int, OrganizationMember> $availableWorkers
 * @property-read Collection<int, OrganizationContract> $partnerContracts
 */
class DispatchCenter extends Component
{
    use EnforcesActiveOrgMembership;

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

    /**
     * Contrats-cadres B2B où MON org est le partenaire prestataire (lecture seule).
     * Isolation stricte : filtré sur provider_organization_id = current_organization_id.
     *
     * @return Collection<int, OrganizationContract>
     */
    public function getPartnerContractsProperty(): Collection
    {
        $orgId = Auth::user()?->current_organization_id;

        if (! $orgId) {
            return OrganizationContract::query()->whereRaw('1 = 0')->get();
        }

        return OrganizationContract::query()
            ->where('provider_organization_id', $orgId)
            ->with(['organizationAccount:id,name', 'rateCards'])
            ->orderByDesc('effective_from')
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
        /*
         * RÉASSIGNER, C'EST AUSSI DÉSASSIGNER (corrigé le 2026-08-05).
         *
         * On ne créait que le nouvel assignment : la mission finissait avec DEUX lignes actives,
         * et `lead_provider_user_id` continuait de désigner le travailleur remplacé. En cascade,
         * le tableau de bord affichait l'ancien (il lit `leadProvider`), l'autorisation Reverb
         * `mission.{id}` lui restait ouverte, et le suivi de trajet le visait encore.
         *
         * On libère donc les leads actifs des AUTRES personnes avant d'installer le nouveau.
         * `reassigned` — et non `cancelled` — parce que l'historique doit distinguer un
         * remplacement d'un abandon.
         */
        /*
         * LA RÈGLE D'ASSIGNATION VIT DÉSORMAIS DANS UN SERVICE PARTAGÉ (2026-08-06).
         *
         * L'API mobile en a besoin à son tour. La recopier aurait créé deux versions d'une règle
         * délicate — libérer les leads actifs des autres, puis synchroniser
         * `lead_provider_user_id` — vouées à diverger au premier ajustement.
         */
        app(MissionAssignmentService::class)->assigner($mission, $worker);

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
            'partnerContracts' => $this->partnerContracts,
        ])->layout('layouts.provider-company');
    }
}

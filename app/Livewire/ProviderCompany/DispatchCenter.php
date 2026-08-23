<?php

namespace App\Livewire\ProviderCompany;

use App\Events\MissionStatusUpdated;
use App\Jobs\Missions\AutoAssignerMissionsJob;
use App\Models\FieldTeam;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\OrganizationMember;
use App\Models\ProviderAgency;
use App\Models\ProviderSiteAssignment;
use App\Models\ProviderSiteTeam;
use App\Services\Client\Calendar\BookingRescheduleService;
use App\Services\Missions\MissionAssignmentService;
use App\Services\Missions\ReassignmentPolicy;
use App\Services\Missions\WorkerAvailabilityService;
use App\Services\PermissionService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @property-read Collection<int, Mission> $missions
 * @property-read Collection<int, OrganizationMember> $availableWorkers
 * @property-read Collection<int, OrganizationContract> $partnerContracts
 * @property-read array<int, bool> $disponibilites
 * @property-read bool $modeContinuActif
 */
class DispatchCenter extends Component
{
    use EnforcesActiveOrgMembership;

    public string $filterDate = '';

    public string $filterStatus = '';

    /** L'IMPLANTATION QU'ON PILOTE — le dépôt de Bruxelles, l'antenne d'Anvers. */
    public ?int $filterAgencyId = null;

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
            // Une mission relève d'une implantation SOIT directement, SOIT par l'équipe qui la porte.
            ->when($this->agenceFiltree(), fn ($q, $agenceId) => $q->where(
                fn ($sous) => $sous->where('provider_agency_id', $agenceId)
                    ->orWhereHas('fieldTeam', fn ($e) => $e->where('provider_agency_id', $agenceId))
            ))
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
            // Filtrer les missions sans filtrer les personnes proposées laisserait suggérer un
            // collègue d'Anvers pour une intervention bruxelloise — l'inverse du but recherché.
            ->when($this->agenceFiltree(), fn ($q, $agenceId) => $q->where('provider_agency_id', $agenceId))
            ->with('user:id,name,profile_photo_path')
            ->get();
    }

    /** L'implantation retenue, si elle appartient bien à cette société. */
    private function agenceFiltree(): ?int
    {
        if ($this->filterAgencyId === null) {
            return null;
        }

        $orgId = Auth::user()?->current_organization_id;

        return ProviderAgency::query()
            ->where('provider_organization_id', $orgId)
            ->whereKey($this->filterAgencyId)
            ->exists()
            ? $this->filterAgencyId
            : null;
    }

    /**
     * Contrats-cadres B2B où MON org est le partenaire prestataire (lecture seule).
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

    /** Ouvrir l'assignation — en proposant le référent du site, quand il y en a un. */
    public function startAssign(int $missionId): void
    {
        $this->assigningId = $missionId;
        $this->assigneeId = null;

        $orgId = Auth::user()?->organizationContextId();

        if (! $orgId) {
            return;
        }

        // Le site est relu DEPUIS la mission, elle-même scopée sur notre organisation : un
        // identifiant reçu ne désigne jamais seul une ressource.
        $siteId = Mission::query()
            ->where('provider_organization_id', $orgId)
            ->whereKey($missionId)
            ->value('organization_site_id');

        if (! $siteId) {
            return;
        }

        // Scopé sur NOTRE organisation : deux prestataires peuvent desservir le même immeuble, et suggérer l'employé d'un concurrent serait à la fois absurde et une fuite.
        $this->assigneeId = ProviderSiteAssignment::query()
            ->where('provider_organization_id', $orgId)
            ->where('organization_site_id', $siteId)
            ->where('role', ProviderSiteAssignment::ROLE_LEAD)
            ->value('user_id');

        // L'ÉQUIPE HABITUELLE DU SITE, pré-proposée elle aussi.
        $this->equipeSuggereeId = ProviderSiteTeam::query()
            ->where('provider_organization_id', $orgId)
            ->where('organization_site_id', $siteId)
            ->value('field_team_id');
    }

    /** L'équipe que le site désigne habituellement — suggestion, jamais décision. */
    #[Locked]
    public ?int $equipeSuggereeId = null;

    public function confirmAssign(): void
    {
        if (! $this->assigningId || ! $this->assigneeId) {
            return;
        }

        $user = Auth::user();

        // LA PERMISSION SE VÉRIFIE AU MOMENT D'AGIR, PAS À L'OUVERTURE DE L'ÉCRAN.
        $mission = Mission::where('provider_organization_id', $user->current_organization_id)
            ->findOrFail($this->assigningId);

        // LA GARDE PORTE SUR LA MISSION, PLUS SEULEMENT SUR LA CLÉ.
        abort_unless(
            app(ReassignmentPolicy::class)->peutReassigner($user, $mission),
            403
        );

        $worker = OrganizationMember::where('organization_account_id', $user->current_organization_id)
            ->where('user_id', $this->assigneeId)
            ->firstOrFail();

        // Créer l'assignment (colonnes réelles de mission_assignments :
        // user_id, role_on_mission, assignment_status, assigned_at —
        // pas de provider_user_id ni assigned_by ; cf. MissionAssignmentStatusService).
        // RÉASSIGNER, C'EST AUSSI DÉSASSIGNER (corrigé le 2026-08-05).
        // LA RÈGLE D'ASSIGNATION VIT DÉSORMAIS DANS UN SERVICE PARTAGÉ (2026-08-06).
        app(MissionAssignmentService::class)->assigner($mission, $worker, $user->id);

        // Broadcast du changement de statut
        broadcast(new MissionStatusUpdated($mission));

        $this->assigningId = 0;
        $this->assigneeId = null;
    }

    /** Confier la mission à une ÉQUIPE entière. */
    public function assignerLEquipe(int $missionId, int $fieldTeamId): void
    {
        $user = Auth::user();
        $orgId = $user?->organizationContextId();

        if (! $orgId) {
            return;
        }

        $mission = Mission::query()
            ->where('provider_organization_id', $orgId)
            ->find($missionId);

        if ($mission === null) {
            return;
        }

        abort_unless(
            app(ReassignmentPolicy::class)->peutReassigner($user, $mission),
            403
        );

        // Scopé dans la requête : l'équipe d'une autre société n'est jamais chargée.
        $equipe = FieldTeam::query()
            ->where('organization_account_id', $orgId)
            ->find($fieldTeamId);

        if ($equipe === null) {
            return;
        }

        if (app(MissionAssignmentService::class)->assignerEquipe($mission, $equipe, $user->id)) {
            broadcast(new MissionStatusUpdated($mission->fresh()));
        }
    }

    /** Ajouter un renfort sur une mission. */
    public function ajouterRenfort(int $missionId, int $userId): void
    {
        [$mission, $membre] = $this->missionEtMembreSousGarde($missionId, $userId);

        if ($mission === null || $membre === null) {
            return;
        }

        app(MissionAssignmentService::class)->ajouterRenfort($mission, $membre);

        broadcast(new MissionStatusUpdated($mission));
    }

    public function retirerRenfort(int $missionId, int $userId): void
    {
        [$mission, $membre] = $this->missionEtMembreSousGarde($missionId, $userId);

        if ($mission === null || $membre === null) {
            return;
        }

        app(MissionAssignmentService::class)->retirerRenfort($mission, $userId);

        broadcast(new MissionStatusUpdated($mission));
    }

    /**
     * La mission et le membre, ou deux `null`.
     *
     * @return array{0: Mission|null, 1: OrganizationMember|null}
     */
    private function missionEtMembreSousGarde(int $missionId, int $userId): array
    {
        $user = Auth::user();
        $orgId = $user?->organizationContextId();

        if (! $orgId) {
            return [null, null];
        }

        abort_unless(
            app(PermissionService::class)->can($user, 'missions.assign', $orgId),
            403
        );

        $mission = Mission::query()
            ->where('provider_organization_id', $orgId)
            ->find($missionId);

        $membre = OrganizationMember::query()
            ->where('organization_account_id', $orgId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        return [$mission, $membre];
    }

    /**
     * Qui est déjà pris sur le créneau de la mission qu'on s'apprête à confier.
     *
     * @return array<int, bool> user_id => libre
     *                          /
     */
    public function getDisponibilitesProperty(): array
    {
        if (! $this->assigningId) {
            return [];
        }

        $mission = $this->missions->firstWhere('id', $this->assigningId);
        $debut = $mission?->planned_start_at;

        if ($mission === null || $debut === null) {
            return [];
        }

        // LA REQUÊTE A ÉTÉ EXTRAITE, PAS RECOPIÉE.
        return app(WorkerAvailabilityService::class)->libresPour(
            organisationId: (int) Auth::user()->current_organization_id,
            debut: $debut,
            fin: $mission->planned_end_at,
            userIds: $this->availableWorkers
                ->pluck('user_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),
            // Se réassigner à soi-même n'est pas un conflit.
            exclureMissionId: $mission->id,
        );
    }

    /** « Assigner tout ce qui n'a personne ». EN FILE, PAS ICI. */
    public function autoAssignerTout(): void
    {
        $user = Auth::user();
        $orgId = $user?->organizationContextId();

        if (! $orgId) {
            return;
        }

        abort_unless(
            app(PermissionService::class)->can($user, 'missions.dispatch', $orgId),
            403
        );

        AutoAssignerMissionsJob::dispatch((int) $orgId, $user->id);
    }

    /** Le MODE CONTINU : toute nouvelle mission de la société est auto-assignée. */
    public function basculerLeModeContinu(): void
    {
        $user = Auth::user();
        $orgId = $user?->organizationContextId();

        if (! $orgId) {
            return;
        }

        abort_unless(
            app(PermissionService::class)->can($user, 'missions.dispatch', $orgId),
            403
        );

        $organisation = OrganizationAccount::find($orgId);

        if ($organisation === null) {
            return;
        }

        $organisation->update(['auto_assign_enabled' => ! $organisation->auto_assign_enabled]);
    }

    public function getModeContinuActifProperty(): bool
    {
        $orgId = Auth::user()?->organizationContextId();

        return $orgId !== null
            && (bool) OrganizationAccount::query()->whereKey($orgId)->value('auto_assign_enabled');
    }

    /** LE DÉPLACEMENT — date, heure et LIEU. */
    #[Locked]
    public int $reprogrammeId = 0;

    public string $nouvelleDate = '';

    public string $nouvelleHeure = '';

    public string $motifReprogrammation = '';

    public function ouvrirLaReprogrammation(int $missionId): void
    {
        $orgId = Auth::user()?->organizationContextId();

        // Scopé dans la requête : un identifiant forgé ne doit pas ouvrir la mission d'un tiers.
        $mission = $orgId === null ? null : Mission::query()
            ->where('provider_organization_id', $orgId)
            ->find($missionId);

        if ($mission === null) {
            return;
        }

        $this->reprogrammeId = $mission->id;
        $this->nouvelleDate = $mission->planned_start_at?->format('Y-m-d') ?? '';
        $this->nouvelleHeure = $mission->planned_start_at?->format('H:i') ?? '';
        $this->motifReprogrammation = '';
    }

    public function fermerLaReprogrammation(): void
    {
        $this->reprogrammeId = 0;
        $this->motifReprogrammation = '';
    }

    public function reprogrammer(): void
    {
        if (! $this->reprogrammeId) {
            return;
        }

        $user = Auth::user();
        $orgId = $user?->organizationContextId();

        if (! $orgId) {
            return;
        }

        abort_unless(
            app(PermissionService::class)->can($user, 'missions.reschedule', $orgId),
            403
        );

        $this->validate([
            'nouvelleDate' => ['required', 'date'],
            'nouvelleHeure' => ['nullable', 'string', 'max:8'],
            'motifReprogrammation' => ['nullable', 'string', 'max:500'],
        ]);

        $mission = Mission::query()
            ->where('provider_organization_id', $orgId)
            ->find($this->reprogrammeId);

        $rendezVous = $mission?->booking;

        if ($rendezVous === null) {
            $this->addError('nouvelleDate', "Cette mission n'a pas de rendez-vous à déplacer.");

            return;
        }

        try {
            app(BookingRescheduleService::class)->reprogrammerParPrestataire(
                rendezVous: $rendezVous,
                acteur: $user,
                nouvelleDate: Carbon::parse($this->nouvelleDate),
                nouvelleHeure: $this->nouvelleHeure !== '' ? $this->nouvelleHeure : null,
                motif: $this->motifReprogrammation !== '' ? $this->motifReprogrammation : null,
            );
        } catch (\DomainException $e) {
            // La fenêtre de gel et le lieu illégitime ne sont pas des refus d'AUTORISATION : la personne avait le droit de déplacer, c'est cette demande-là qui ne passe pas.
            $this->addError('nouvelleDate', $e->getMessage());

            return;
        }

        $this->fermerLaReprogrammation();
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
            'disponibilites' => $this->disponibilites,
            // Vide pour une société mono-implantation : la vue n'affiche alors pas le filtre.
            'agences' => ProviderAgency::query()
                ->where('provider_organization_id', Auth::user()?->current_organization_id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name']),
        ])->layout('layouts.provider-company');
    }
}

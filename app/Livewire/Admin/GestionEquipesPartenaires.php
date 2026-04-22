<?php

namespace App\Livewire\Admin;

use App\Models\Country;
use App\Models\FieldTeam;
use App\Models\FieldTeamMember;
use App\Models\OrganizationAccount;
use App\Models\PartnerZoneCoverage;
use App\Models\ServiceCatalog;
use App\Models\ServicePartner;
use App\Models\ServiceZone;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class GestionEquipesPartenaires extends Component
{
    public ?int $selectedTeamId = null;
    public ?int $selectedPartnerId = null;

    public array $teamForm = [];
    public array $memberForm = [];
    public array $partnerForm = [];
    public array $coverageForm = [];

    public function mount(): void
    {
        Gate::authorize('manage-entreprises');
        $this->resetTeamForm();
        $this->resetMemberForm();
        $this->resetPartnerForm();
        $this->resetCoverageForm();
    }

    protected function resetTeamForm(): void
    {
        $this->teamForm = [
            'name' => '',
            'country_id' => Country::query()->value('id'),
            'service_zone_id' => null,
            'organization_account_id' => null,
            'service_partner_id' => null,
            'team_lead_user_id' => null,
            'status' => 'active',
            'is_internal' => true,
            'max_concurrent_missions' => null,
            'notes' => null,
        ];
    }

    protected function resetMemberForm(): void
    {
        $this->memberForm = [
            'user_id' => null,
            'role_on_team' => 'agent',
            'is_team_lead' => false,
        ];
    }

    protected function resetPartnerForm(): void
    {
        $this->partnerForm = [
            'name' => '',
            'legal_name' => '',
            'country_id' => Country::query()->value('id'),
            'status' => 'active',
            'email' => null,
            'phone' => null,
            'billing_email' => null,
            'quality_score' => null,
            'is_active' => true,
            'notes' => null,
        ];
    }

    protected function resetCoverageForm(): void
    {
        $this->coverageForm = [
            'service_zone_id' => null,
            'service_catalog_id' => null,
            'priority' => 1,
            'max_daily_capacity' => null,
            'sla_response_hours' => null,
        ];
    }

    public function selectTeam(int $teamId): void
    {
        $team = FieldTeam::findOrFail($teamId);
        $this->selectedTeamId = $team->id;
        $this->teamForm = [
            'name' => $team->name,
            'country_id' => $team->country_id,
            'service_zone_id' => $team->service_zone_id,
            'organization_account_id' => $team->organization_account_id,
            'service_partner_id' => $team->service_partner_id,
            'team_lead_user_id' => $team->team_lead_user_id,
            'status' => $team->status,
            'is_internal' => (bool) $team->is_internal,
            'max_concurrent_missions' => $team->max_concurrent_missions,
            'notes' => $team->notes,
        ];
    }

    public function newTeam(): void
    {
        $this->selectedTeamId = null;
        $this->resetTeamForm();
    }

    public function saveTeam(): void
    {
        Gate::authorize('manage-entreprises');

        $validated = $this->validate([
            'teamForm.name' => ['required', 'string', 'max:255'],
            'teamForm.country_id' => ['nullable', 'exists:countries,id'],
            'teamForm.service_zone_id' => ['nullable', 'exists:service_zones,id'],
            'teamForm.organization_account_id' => ['nullable', 'exists:organization_accounts,id'],
            'teamForm.service_partner_id' => ['nullable', 'exists:service_partners,id'],
            'teamForm.team_lead_user_id' => ['nullable', 'exists:users,id'],
            'teamForm.status' => ['required', Rule::in(['active', 'inactive', 'pilot'])],
            'teamForm.is_internal' => ['boolean'],
            'teamForm.max_concurrent_missions' => ['nullable', 'integer', 'min:1', 'max:100'],
            'teamForm.notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data = $validated['teamForm'];
        $slug = Str::slug($data['name']);
        $team = FieldTeam::query()->find($this->selectedTeamId);

        if (! $team) {
            $team = FieldTeam::create(array_merge($data, [
                'slug' => Str::limit($slug . '-' . Str::lower(Str::random(6)), 255, ''),
            ]));
            $this->selectedTeamId = $team->id;
        } else {
            $team->update($data);
        }

        if (! empty($data['team_lead_user_id'])) {
            FieldTeamMember::updateOrCreate(
                ['field_team_id' => $team->id, 'user_id' => $data['team_lead_user_id']],
                [
                    'role_on_team' => 'team_lead',
                    'is_team_lead' => true,
                    'is_active' => true,
                    'joined_at' => now(),
                    'left_at' => null,
                ]
            );
        }

        ActivityLogger::log('field_team_saved', $team, [
            'team_id' => $team->id,
            'status' => $team->status,
            'is_internal' => $team->is_internal,
        ]);

        $this->dispatch('toast', 'Équipe enregistrée.', 'success');
    }

    public function addMember(): void
    {
        Gate::authorize('manage-entreprises');
        abort_unless($this->selectedTeamId, 404);

        $validated = $this->validate([
            'memberForm.user_id' => ['required', 'exists:users,id'],
            'memberForm.role_on_team' => ['required', Rule::in(['team_lead', 'senior_agent', 'agent', 'specialist', 'driver'])],
            'memberForm.is_team_lead' => ['boolean'],
        ]);

        $member = FieldTeamMember::updateOrCreate(
            ['field_team_id' => $this->selectedTeamId, 'user_id' => $validated['memberForm']['user_id']],
            [
                'role_on_team' => $validated['memberForm']['role_on_team'],
                'is_team_lead' => (bool) $validated['memberForm']['is_team_lead'],
                'is_active' => true,
                'joined_at' => now(),
                'left_at' => null,
            ]
        );

        if ($member->is_team_lead) {
            FieldTeam::query()->whereKey($this->selectedTeamId)->update(['team_lead_user_id' => $member->user_id]);
        }

        ActivityLogger::log('field_team_member_saved', $member->fieldTeam, [
            'member_user_id' => $member->user_id,
            'role_on_team' => $member->role_on_team,
        ]);

        $this->resetMemberForm();
        $this->dispatch('toast', 'Membre ajouté à l’équipe.', 'success');
    }

    public function selectPartner(int $partnerId): void
    {
        $partner = ServicePartner::findOrFail($partnerId);
        $this->selectedPartnerId = $partner->id;
        $this->partnerForm = [
            'name' => $partner->name,
            'legal_name' => $partner->legal_name,
            'country_id' => $partner->country_id,
            'status' => $partner->status,
            'email' => $partner->email,
            'phone' => $partner->phone,
            'billing_email' => $partner->billing_email,
            'quality_score' => $partner->quality_score,
            'is_active' => (bool) $partner->is_active,
            'notes' => $partner->notes,
        ];
    }

    public function newPartner(): void
    {
        $this->selectedPartnerId = null;
        $this->resetPartnerForm();
    }

    public function savePartner(): void
    {
        Gate::authorize('manage-entreprises');

        $validated = $this->validate([
            'partnerForm.name' => ['required', 'string', 'max:255'],
            'partnerForm.legal_name' => ['nullable', 'string', 'max:255'],
            'partnerForm.country_id' => ['nullable', 'exists:countries,id'],
            'partnerForm.status' => ['required', Rule::in(['active', 'inactive', 'pilot'])],
            'partnerForm.email' => ['nullable', 'email', 'max:255'],
            'partnerForm.phone' => ['nullable', 'string', 'max:50'],
            'partnerForm.billing_email' => ['nullable', 'email', 'max:255'],
            'partnerForm.quality_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'partnerForm.is_active' => ['boolean'],
            'partnerForm.notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data = $validated['partnerForm'];
        $slug = Str::slug($data['name']);
        $partner = ServicePartner::query()->find($this->selectedPartnerId);

        if (! $partner) {
            $partner = ServicePartner::create(array_merge($data, [
                'slug' => Str::limit($slug . '-' . Str::lower(Str::random(6)), 255, ''),
            ]));
            $this->selectedPartnerId = $partner->id;
        } else {
            $partner->update($data);
        }

        ActivityLogger::log('service_partner_saved', $partner, [
            'partner_id' => $partner->id,
            'status' => $partner->status,
        ]);

        $this->dispatch('toast', 'Partenaire enregistré.', 'success');
    }

    public function addCoverage(): void
    {
        Gate::authorize('manage-entreprises');
        abort_unless($this->selectedPartnerId, 404);

        $validated = $this->validate([
            'coverageForm.service_zone_id' => ['required', 'exists:service_zones,id'],
            'coverageForm.service_catalog_id' => ['nullable', 'exists:service_catalogs,id'],
            'coverageForm.priority' => ['required', 'integer', 'min:1', 'max:100'],
            'coverageForm.max_daily_capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'coverageForm.sla_response_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
        ]);

        PartnerZoneCoverage::updateOrCreate(
            [
                'service_partner_id' => $this->selectedPartnerId,
                'service_zone_id' => $validated['coverageForm']['service_zone_id'],
                'service_catalog_id' => $validated['coverageForm']['service_catalog_id'],
            ],
            [
                'coverage_status' => 'active',
                'priority' => $validated['coverageForm']['priority'],
                'max_daily_capacity' => $validated['coverageForm']['max_daily_capacity'],
                'sla_response_hours' => $validated['coverageForm']['sla_response_hours'],
            ]
        );

        $this->resetCoverageForm();
        $this->dispatch('toast', 'Couverture partenaire enregistrée.', 'success');
    }

    public function getCountriesProperty()
    {
        return Country::query()->orderBy('name')->get();
    }

    public function getZonesProperty()
    {
        return ServiceZone::query()->orderBy('name')->get();
    }

    public function getAccountsProperty()
    {
        return OrganizationAccount::query()->orderBy('name')->get();
    }

    public function getEmployeesProperty()
    {
        return User::query()->where('role', User::ROLE_EMPLOYE)->orderBy('name')->get();
    }

    public function getServicesProperty()
    {
        return ServiceCatalog::query()->orderBy('name')->get();
    }

    public function getTeamsProperty()
    {
        return FieldTeam::query()->with(['serviceZone', 'organizationAccount', 'servicePartner', 'teamLead'])->orderBy('name')->get();
    }

    public function getPartnersProperty()
    {
        return ServicePartner::query()->with('country')->orderBy('name')->get();
    }

    public function getSelectedTeamProperty(): ?FieldTeam
    {
        return $this->selectedTeamId
            ? FieldTeam::query()->with(['activeMembers.user', 'serviceZone', 'teamLead', 'servicePartner', 'organizationAccount'])->find($this->selectedTeamId)
            : null;
    }

    public function getSelectedPartnerProperty(): ?ServicePartner
    {
        return $this->selectedPartnerId
            ? ServicePartner::query()->with(['country', 'zoneCoverages.serviceZone', 'zoneCoverages.serviceCatalog', 'fieldTeams'])->find($this->selectedPartnerId)
            : null;
    }

    public function render()
    {
        return view('livewire.admin.gestion-equipes-partenaires', [
            'teams' => $this->teams,
            'partners' => $this->partners,
            'selectedTeam' => $this->selectedTeam,
            'selectedPartner' => $this->selectedPartner,
            'countries' => $this->countries,
            'zones' => $this->zones,
            'accounts' => $this->accounts,
            'employees' => $this->employees,
            'services' => $this->services,
        ])->layout('layouts.app');
    }
}

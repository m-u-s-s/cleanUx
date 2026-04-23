<?php

namespace App\Livewire\Admin;

use App\Models\MissionBatch;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\ServicePartner;
use App\Services\Missions\MissionBatchPlannerService;
use Livewire\Component;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;

class OrchestrationTerrainCenter extends Component
{
    public ?int $organization_account_id = null;
    public ?int $organization_site_id = null;
    public ?int $field_team_id = null;
    public ?int $service_partner_id = null;
    public string $name = '';
    public string $starts_on = '';
    public string $ends_on = '';
    public string $batch_type = 'multi_day_site';
    public int $segments_per_day = 1;
    public int $crew_size = 2;
    public int $estimated_segment_minutes = 180;
    public ?string $notes = null;

    public function mount(): void
    {
        $this->starts_on = now()->toDateString();
        $this->ends_on = now()->addDay()->toDateString();
    }

    public function createBatch(MissionBatchPlannerService $planner): void
    {
        $this->validate([
            'organization_account_id' => ['nullable', 'exists:organization_accounts,id'],
            'organization_site_id' => ['nullable', 'exists:organization_sites,id'],
            'field_team_id' => ['nullable', 'exists:field_teams,id'],
            'service_partner_id' => ['nullable', 'exists:service_partners,id'],
            'name' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'batch_type' => ['required', 'string', 'max:50'],
            'segments_per_day' => ['required', 'integer', 'min:1', 'max:10'],
            'crew_size' => ['required', 'integer', 'min:1', 'max:20'],
            'estimated_segment_minutes' => ['required', 'integer', 'min:30', 'max:1440'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $planner->createBatch([
            'organization_account_id' => $this->organization_account_id,
            'organization_site_id' => $this->organization_site_id,
            'field_team_id' => $this->field_team_id,
            'service_partner_id' => $this->service_partner_id,
            'name' => $this->name,
            'starts_on' => $this->starts_on,
            'ends_on' => $this->ends_on,
            'batch_type' => $this->batch_type,
            'segments_per_day' => $this->segments_per_day,
            'crew_size' => $this->crew_size,
            'estimated_segment_minutes' => $this->estimated_segment_minutes,
            'notes' => $this->notes,
        ]);

        $this->reset('name', 'organization_account_id', 'organization_site_id', 'field_team_id', 'service_partner_id', 'notes');
        $this->starts_on = now()->toDateString();
        $this->ends_on = now()->addDay()->toDateString();

        $this->dispatch('toast', 'Lot de mission créé.', 'success');
    }

    public function getAccountsProperty()
    {
        return OrganizationAccount::orderBy('name')->get();
    }

    public function getSitesProperty()
    {
        return OrganizationSite::when($this->organization_account_id, fn ($q) => $q->where('organization_account_id', $this->organization_account_id))
            ->orderBy('name')
            ->get();
    }

    public function getTeamsProperty()
    {
        return class_exists(\App\Models\FieldTeam::class)
            ? \App\Models\FieldTeam::query()->orderBy('name')->get()
            : collect();
    }

    public function getPartnersProperty()
    {
        return class_exists(ServicePartner::class)
            ? ServicePartner::query()->orderBy('name')->get()
            : collect();
    }

    public function getRecentBatchesProperty()
    {
        return MissionBatch::with(['organizationAccount', 'organizationSite', 'days'])
            ->latest()
            ->limit(10)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.admin.orchestration-terrain-center', [
            'accounts' => $this->accounts,
            'sites' => $this->sites,
            'teams' => $this->teams,
            'partners' => $this->partners,
            'recentBatches' => $this->recentBatches,
        ]);
    }
}

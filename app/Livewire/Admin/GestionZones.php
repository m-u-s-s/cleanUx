<?php

namespace App\Livewire\Admin;

use App\Models\ServiceZone;
use App\Support\Livewire\Concerns\Admin\ManagesTradeZoneSettings;
use App\Support\Livewire\Concerns\Admin\ManagesZonesData;
use App\Support\Livewire\Concerns\Admin\PerformsZoneManagementActions;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class GestionZones extends Component
{
    use EnforcesAdminAccess;
    use ManagesTradeZoneSettings;
    use ManagesZonesData {
        selectZone as protected selectZoneBase;
    }
    use PerformsZoneManagementActions;
    use WithPagination;

    /**
     * LA CAPACITE QUE PORTAIT SA ROUTE, AVANT LA FUSION DANS LE CATALOGUE.
     * Elle vit ici et non sur la route : `/livewire/update` n'en rejoue aucune. Et c'est
     * `boot()` et non `bootQuelqueChose()` : Livewire n'appelle que `boot()` et `boot{Trait}`.
     */
    public function boot(): void
    {
        abort_unless(Gate::allows('manage-services'), 403);
    }

    public function selectZone(int $zoneId): void
    {
        $this->selectZoneBase($zoneId);
        $this->loadTradeSettingsForZone($zoneId);
    }

    public string $search = '';

    public string $statusFilter = '';

    public string $regionFilter = '';

    public string $provinceFilter = '';

    public string $bookableFilter = '';

    public string $visibilityFilter = '';

    public string $coverageFilter = '';

    public ?int $selectedZoneId = null;

    public string $name = '';

    public string $code = '';

    public string $coverage_type = 'custom';

    public string $status = 'active';

    public bool $is_bookable = true;

    public bool $is_visible = true;

    public int $priority = 100;

    public int $minimum_notice_hours = 24;

    public ?int $maximum_daily_jobs = null;

    public float $travel_surcharge = 0;

    public int $time_buffer_minutes = 0;

    public string $notes = '';

    public string $employeeToAssign = '';

    public string $assignmentType = 'primary';

    public int $assignmentPriority = 100;

    public string $assignmentNotes = '';

    public string $copyRulesFromZoneId = '';

    public array $serviceRules = [];

    public array $assignmentEdits = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'regionFilter' => ['except' => ''],
        'provinceFilter' => ['except' => ''],
        'bookableFilter' => ['except' => ''],
        'visibilityFilter' => ['except' => ''],
        'coverageFilter' => ['except' => ''],
    ];

    public function mount(): void
    {
        $firstZone = ServiceZone::query()->orderBy('priority')->orderBy('name')->first();

        if ($firstZone) {
            $this->selectZone($firstZone->id);
        }
    }

    public function render(): View
    {
        $zones = $this->applyZoneFilters($this->zoneBaseQuery())->orderBy('priority')->orderBy('name')->paginate(12);

        if (! $this->selectedZoneId && $zones->count() > 0) {
            $this->selectZone((int) $zones->first()->id);
        }

        return view('livewire.admin.gestion-zones', [
            'zones' => $zones,
            'selectedZone' => $this->selectedZone,
            'regions' => $this->regions,
            'provinces' => $this->provinces,
            'availableEmployees' => $this->availableEmployees,
            'zoneHistory' => $this->zoneHistory,
            'zoneStats' => $this->zoneStats,
            'sourceZones' => $this->sourceZones,
        ]);
    }
}

<?php

namespace App\Livewire\Admin;

use App\Models\Mission;
use Livewire\Component;
use Livewire\WithPagination;

class MissionAdvancedSearch extends Component
{
    use WithPagination;

    public $status = '';
    public $qualityStatus = '';
    public $employee = '';
    public $service = '';
    public $dateFrom = '';
    public $dateTo = '';

    public function updating($prop)
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = Mission::query()
            ->with(['rendezVous', 'leadEmployee', 'serviceCatalog']);

        $query->when($this->status, fn($q) => $q->where('status', $this->status));
        $query->when($this->qualityStatus, fn($q) => $q->where('quality_status', $this->qualityStatus));
        $query->when($this->employee, fn($q) => $q->where('lead_employee_id', $this->employee));
        $query->when($this->service, fn($q) => $q->where('service_catalog_id', $this->service));

        $query->when($this->dateFrom, fn($q) => $q->whereDate('created_at', '>=', $this->dateFrom));
        $query->when($this->dateTo, fn($q) => $q->whereDate('created_at', '<=', $this->dateTo));

        return view('livewire.admin.mission-advanced-search', [
            'missions' => $query->latest()->paginate(15),
        ]);
    }
}
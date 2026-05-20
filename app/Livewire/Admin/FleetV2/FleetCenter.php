<?php

namespace App\Livewire\Admin\FleetV2;

use App\Models\FleetAssignment;
use App\Models\FleetCertification;
use App\Models\FleetEquipment;
use App\Models\FleetMaintenanceLog;
use App\Models\FleetVehicle;
use App\Services\FleetV2\CertificationExpiryScanner;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class FleetCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'vehicles';   // vehicles | equipment | assignments | maintenance | certifications
    public string $filterStatus = '';

    public function scanExpiring(): void
    {
        $counts = app(CertificationExpiryScanner::class)->scanAndUpdate();
        $this->dispatch('toast', 'Scan terminé : ' . array_sum($counts) . ' certifications mises à jour.', 'success');
    }

    public function render(): View
    {
        $expiringSoonDays = (int) config('fleet_v2.expiring_soon_days', 30);
        $kpis = [
            'vehicles_total' => FleetVehicle::query()->count(),
            'vehicles_available' => FleetVehicle::query()->available()->count(),
            'vehicles_in_use' => FleetVehicle::query()->where('status', FleetVehicle::STATUS_IN_USE)->count(),
            'equipment_total' => FleetEquipment::query()->count(),
            'assignments_active' => FleetAssignment::query()->active()->count(),
            'certs_expiring_soon' => FleetCertification::query()
                ->where('status', FleetCertification::STATUS_EXPIRING_SOON)
                ->count(),
            'certs_expired' => FleetCertification::query()
                ->where('status', FleetCertification::STATUS_EXPIRED)
                ->count(),
        ];

        if ($this->tab === 'vehicles') {
            $items = FleetVehicle::query()
                ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
                ->orderBy('plate')
                ->paginate(25);
        } elseif ($this->tab === 'equipment') {
            $items = FleetEquipment::query()
                ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
                ->orderBy('name')
                ->paginate(25);
        } elseif ($this->tab === 'assignments') {
            $items = FleetAssignment::query()
                ->with(['vehicle:id,code,plate', 'equipment:id,code,name', 'provider:id,email'])
                ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
                ->orderByDesc('assigned_at')
                ->paginate(25);
        } elseif ($this->tab === 'maintenance') {
            $items = FleetMaintenanceLog::query()
                ->with(['vehicle:id,code,plate', 'equipment:id,code,name'])
                ->orderByDesc('performed_at')
                ->paginate(25);
        } else {
            $items = FleetCertification::query()
                ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
                ->orderBy('expires_at')
                ->paginate(25);
        }

        return view('livewire.admin.fleet-v2.fleet-center', [
            'kpis' => $kpis,
            'items' => $items,
            'expiringSoonDays' => $expiringSoonDays,
        ]);
    }
}

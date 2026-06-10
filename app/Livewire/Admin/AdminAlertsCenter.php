<?php

namespace App\Livewire\Admin;

use App\Services\Admin\AdminAlertService;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Livewire\Component;

class AdminAlertsCenter extends Component
{
    use EnforcesAdminAccess;

    public array $alerts = [];

    public function mount(AdminAlertService $service): void
    {
        $this->alerts = $service->alerts();
    }

    public function refreshAlerts(AdminAlertService $service): void
    {
        $this->alerts = $service->alerts();
    }

    public function render()
    {
        return view('livewire.admin.admin-alerts-center');
    }
}

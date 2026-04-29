<?php

namespace App\Livewire\Admin;

use App\Services\Admin\AdminAlertService;
use Livewire\Component;

class AdminAlertsCenter extends Component
{
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
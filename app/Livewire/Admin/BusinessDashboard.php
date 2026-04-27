<?php

namespace App\Livewire\Admin;

use App\Services\Analytics\BusinessDashboardService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class BusinessDashboard extends Component
{
    public function render(BusinessDashboardService $service): View
    {
        return view('livewire.admin.business-dashboard', [
            'metrics' => $service->metrics(),
        ]);
    }
}
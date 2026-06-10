<?php

namespace App\Livewire\Admin;

use App\Services\Admin\EmployeePerformanceService;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Livewire\Component;

class EmployeePerformance extends Component
{
    use EnforcesAdminAccess;

    public array $employees = [];

    public function mount(EmployeePerformanceService $service): void
    {
        $this->employees = $service->get();
    }

    public function render()
    {
        return view('livewire.admin.employee-performance');
    }
}

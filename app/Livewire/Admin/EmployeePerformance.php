<?php

namespace App\Livewire\Admin;

use App\Services\Admin\EmployeePerformanceService;
use Livewire\Component;

class EmployeePerformance extends Component
{
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
<?php

namespace App\Support\Livewire\Concerns;

use App\Support\Livewire\Concerns\Admin\BootsAdminDashboardFilters;
use App\Support\Livewire\Concerns\Admin\ComputesAdminDashboardInsights;
use App\Support\Livewire\Concerns\Admin\ComputesAdminDashboardScopes;
use App\Support\Livewire\Concerns\Admin\ComputesPlatformHealth;

trait ComputesAdminDashboardData
{
    use BootsAdminDashboardFilters;
    use ComputesAdminDashboardInsights;
    use ComputesAdminDashboardScopes;
    use ComputesPlatformHealth;
}

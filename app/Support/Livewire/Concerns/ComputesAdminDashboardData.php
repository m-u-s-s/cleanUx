<?php

namespace App\Support\Livewire\Concerns;

use App\Support\Livewire\Concerns\Admin\BootsAdminDashboardFilters;
use App\Support\Livewire\Concerns\Admin\ComputesAdminDashboardInsights;
use App\Support\Livewire\Concerns\Admin\ComputesAdminDashboardScopes;

trait ComputesAdminDashboardData
{
    use BootsAdminDashboardFilters;
    use ComputesAdminDashboardScopes;
    use ComputesAdminDashboardInsights;
}

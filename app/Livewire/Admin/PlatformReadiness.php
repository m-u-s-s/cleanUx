<?php

namespace App\Livewire\Admin;

use App\Services\System\PlatformReadinessService;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Livewire\Component;

class PlatformReadiness extends Component
{
    use EnforcesAdminAccess;

    public function render(PlatformReadinessService $service)
    {
        return view('livewire.admin.platform-readiness', [
            'checks' => $service->check(),
        ]);
    }
}

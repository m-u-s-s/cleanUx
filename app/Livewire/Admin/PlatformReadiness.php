<?php

namespace App\Livewire\Admin;

use App\Services\System\PlatformReadinessService;
use Livewire\Component;

class PlatformReadiness extends Component
{
    public function render(PlatformReadinessService $service)
    {
        return view('livewire.admin.platform-readiness', [
            'checks' => $service->check(),
        ]);
    }
}
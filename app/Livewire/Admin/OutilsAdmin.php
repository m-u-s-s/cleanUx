<?php

namespace App\Livewire\Admin;

use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Livewire\Component;

class OutilsAdmin extends Component
{
    use EnforcesAdminAccess;

    public function render()
    {
        return view('livewire.admin.outils-admin');
    }
}

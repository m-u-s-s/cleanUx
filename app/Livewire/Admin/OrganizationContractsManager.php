<?php

namespace App\Livewire\Admin;

use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Livewire\Component;

class OrganizationContractsManager extends Component
{
    use EnforcesAdminAccess;

    public int $organization_id;

    public function render()
    {
        return view('livewire.admin.organization-contracts-manager', [
            'contracts' => OrganizationContract::where('organization_account_id', $this->organization_id)->get(),
            'organization' => OrganizationAccount::find($this->organization_id),
        ]);
    }
}

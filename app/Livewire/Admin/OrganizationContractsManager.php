<?php

namespace App\Livewire\Admin;

use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use Livewire\Component;

class OrganizationContractsManager extends Component
{
    public int $organization_id;

    public function render()
    {
        return view('livewire.admin.organization-contracts-manager', [
            'contracts' => OrganizationContract::where('organization_account_id', $this->organization_id)->get(),
            'organization' => OrganizationAccount::find($this->organization_id),
        ]);
    }
}
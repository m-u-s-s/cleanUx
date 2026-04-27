<?php

namespace App\Livewire\Admin;

use App\Models\OrganizationSite;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class OrganizationSitesManager extends Component
{
    public string $name = '';
    public string $address = '';
    public string $city = '';
    public string $cost_center = '';

    public function create()
    {
        OrganizationSite::create([
            'organization_account_id' => Auth::user()->organization_account_id,
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'cost_center' => $this->cost_center,
        ]);

        $this->reset();

        $this->dispatch('toast', 'Site ajouté', 'success');
    }

    public function render()
    {
        return view('livewire.admin.organization-sites-manager', [
            'sites' => OrganizationSite::where(
                'organization_account_id',
                Auth::user()->organization_account_id
            )->get()
        ]);
    }
}
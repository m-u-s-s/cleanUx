<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Livewire\Component;
use Illuminate\Contracts\View\View;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

class AdminPremiumClients extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterPlan = 'all';
    public string $filterStatus = 'all';

    protected $paginationTheme = 'tailwind';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFilterPlan()
    {
        $this->resetPage();
    }

    public function updatingFilterStatus()
    {
        $this->resetPage();
    }

    public function getClientsProperty()
    {
        return User::query()
            ->with('favoriteEmployes')
            ->clientFacing()
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%'.$this->search.'%')
                      ->orWhere('email', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->filterPlan !== 'all', fn ($q) => $q->where('plan_type', $this->filterPlan))
            ->when($this->filterStatus !== 'all', fn ($q) => $q->where('plan_status', $this->filterStatus))
            ->latest()
            ->paginate(10);
    }

    public function setPremium(int $clientId): void
    {
        $client = User::clientFacing()->findOrFail($clientId);

        $client->update([
            'plan_type' => 'premium',
            'plan_status' => 'active',
            'premium_started_at' => now(),
            'premium_renewal_at' => now()->addMonth(),
        ]);

        session()->flash('success', 'Le client est maintenant Premium.');
    }

    public function setStandard(int $clientId): void
    {
        $client = User::clientFacing()->findOrFail($clientId);

        $subscription = $client->subscription('default');
        if ($subscription && ! $subscription->ended()) {
            $subscription->cancelNow();
        }

        $client->update([
            'plan_type' => 'standard',
            'plan_status' => 'inactive',
            'premium_started_at' => null,
            'premium_renewal_at' => null,
        ]);

        session()->flash('success', 'Le client est repassé en Standard.');
    }

    public function suspendPlan(int $clientId): void
    {
        $client = User::clientFacing()->findOrFail($clientId);

        $client->update([
            'plan_status' => 'past_due',
        ]);

        session()->flash('success', 'Le plan du client a été suspendu.');
    }

    public function reactivatePlan(int $clientId): void
    {
        $client = User::clientFacing()->findOrFail($clientId);

        $client->update([
            'plan_type' => 'premium',
            'plan_status' => 'active',
            'premium_renewal_at' => now()->addMonth(),
        ]);

        session()->flash('success', 'Le plan Premium a été réactivé.');
    }

    public function render(): View
    {
        return view('livewire.admin.admin-premium-clients', [
            'clients' => $this->clients,
        ]);
    }
}
<?php

namespace App\Livewire\Admin\TenancyV2;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantUser;
use App\Services\TenancyV2\TenantService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class TenantsCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'tenants';   // tenants | domains | users
    public string $filterStatus = '';
    public string $filterPlan = '';

    public function suspendTenant(int $tenantId, string $reason = 'Suspendu via admin UI'): void
    {
        $tenant = Tenant::findOrFail($tenantId);
        try {
            app(TenantService::class)->suspend($tenant, $reason);
            $this->dispatch('toast', 'Tenant suspendu.', 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur : ' . $e->getMessage(), 'error');
        }
    }

    public function activateTenant(int $tenantId): void
    {
        $tenant = Tenant::findOrFail($tenantId);
        app(TenantService::class)->activate($tenant);
        $this->dispatch('toast', 'Tenant activé.', 'success');
    }

    public function archiveTenant(int $tenantId): void
    {
        $tenant = Tenant::findOrFail($tenantId);
        app(TenantService::class)->archive($tenant);
        $this->dispatch('toast', 'Tenant archivé.', 'success');
    }

    public function verifyDomain(int $domainId): void
    {
        $domain = TenantDomain::findOrFail($domainId);
        $domain->update([
            'is_verified' => true,
            'verified_at' => now(),
            'ssl_status' => TenantDomain::SSL_READY,
        ]);
        $this->dispatch('toast', 'Domaine vérifié.', 'success');
    }

    public function render(): View
    {
        $kpis = [
            'tenants_total' => Tenant::query()->count(),
            'tenants_active' => Tenant::query()->where('status', Tenant::STATUS_ACTIVE)->count(),
            'tenants_trial' => Tenant::query()->where('status', Tenant::STATUS_TRIAL)->count(),
            'tenants_suspended' => Tenant::query()->where('status', Tenant::STATUS_SUSPENDED)->count(),
            'domains_verified' => TenantDomain::query()->where('is_verified', true)->count(),
        ];

        if ($this->tab === 'tenants') {
            $items = Tenant::query()
                ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
                ->when($this->filterPlan, fn ($q) => $q->where('plan_code', $this->filterPlan))
                ->orderByDesc('created_at')
                ->paginate(25);
        } elseif ($this->tab === 'domains') {
            $items = TenantDomain::query()
                ->with('tenant:id,code,name')
                ->orderByDesc('created_at')
                ->paginate(25);
        } else {
            $items = TenantUser::query()
                ->with(['tenant:id,code,name', 'user:id,email,name'])
                ->orderByDesc('created_at')
                ->paginate(25);
        }

        return view('livewire.admin.tenancy-v2.tenants-center', [
            'kpis' => $kpis,
            'items' => $items,
        ]);
    }
}

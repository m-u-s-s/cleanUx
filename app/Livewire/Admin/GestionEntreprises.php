<?php

namespace App\Livewire\Admin;

use App\Support\Livewire\Concerns\Admin\ManagesEntrepriseAccounts;
use App\Support\Livewire\Concerns\Admin\ManagesEntrepriseSitesAndUsers;
use App\Models\OrganizationAccount;
use Livewire\Component;
use Livewire\WithPagination;

class GestionEntreprises extends Component
{
    use ManagesEntrepriseAccounts;
    use ManagesEntrepriseSitesAndUsers;
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    public string $search = '';
    public string $status = '';
    public string $type = '';
    public string $zoneFilter = '';
    public ?int $selectedAccountId = null;
    public ?int $accountId = null;
    public string $name = '';
    public string $legal_name = '';
    public string $slug = '';
    public string $account_type = 'entreprise';
    public string $tva_number = '';
    public string $email = '';
    public string $phone = '';
    public string $billing_email = '';
    public string $account_status = 'active';
    public string $address_line_1 = '';
    public string $address_line_2 = '';
    public string $city = '';
    public string $postal_code = '';
    public bool $is_multisite = true;
    public bool $is_key_account = false;
    public string $notes = '';
    public string $contract_reference = '';
    public string $pricing_profile = '';
    public string $sla_hours = '';
    public string $priority_zone_id = '';
    public string $approval_mode = 'auto';
    public bool $purchase_order_required = false;
    public string $default_cost_center = '';
    public string $negotiated_discount_percent = '';
    public string $payment_terms_days = '';
    public string $contract_status_value = 'draft';
    public array $zone_priority_ids = [];
    public bool $require_po = false;
    public ?int $siteId = null;
    public string $site_name = '';
    public string $site_code = '';
    public string $site_contact_name = '';
    public string $site_email = '';
    public string $site_phone = '';
    public string $site_address_line_1 = '';
    public string $site_address_line_2 = '';
    public string $site_city = '';
    public string $site_postal_code = '';
    public string $site_access_instructions = '';
    public string $site_zone_id = '';
    public string $site_approval_mode = 'inherit';
    public bool $site_purchase_order_required = false;
    public string $site_default_cost_center = '';
    public string $site_priority_level = '';
    public bool $site_requires_manual_validation = false;
    public string $site_tags = '';
    public bool $site_is_primary = false;
    public bool $site_is_active = true;
    public string $user_to_attach = '';
    public string $user_role_mode = 'keep';
    public string $user_site_scope_mode = 'all';
    public string $user_site_scope = 'all';
    public array $user_allowed_site_ids = [];
    public array $user_site_ids = [];
    public string $user_contact_role = '';

    protected $queryString = ['search', 'status', 'type', 'zoneFilter', 'page'];

    public function render()
    {
        $accounts = OrganizationAccount::query()
            ->withCount(['sites', 'users', 'rendezVous'])
            ->with(['postalCodeReference', 'users'])
            ->when($this->search !== '', function ($query) {
                $query->where(function ($inner) {
                    $inner->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('legal_name', 'like', '%'.$this->search.'%')
                        ->orWhere('slug', 'like', '%'.$this->search.'%')
                        ->orWhere('tva_number', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->type !== '', fn ($query) => $query->where('type', $this->type))
            ->when($this->zoneFilter !== '', function ($query) {
                $query->whereHas('sites', fn ($siteQuery) => $siteQuery->where('service_zone_id', $this->zoneFilter));
            })
            ->orderByDesc('is_key_account')
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.admin.gestion-entreprises', [
            'accounts' => $accounts,
            'selectedAccount' => $this->selectedAccount,
            'zones' => $this->zones,
            'availableUsers' => $this->availableUsers,
            'logs' => $this->entrepriseActivityLogs(),
        ]);
    }
}

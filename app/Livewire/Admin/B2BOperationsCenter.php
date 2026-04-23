<?php

namespace App\Livewire\Admin;

use App\Models\EnterpriseWorkOrder;
use App\Models\FieldTeam;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\ServiceCatalog;
use App\Models\ServicePartner;
use App\Models\ServiceZone;
use App\Models\User;
use App\Models\WorkOrderApproval;
use App\Models\WorkOrderLine;
use App\Support\ActivityLogger;
use Illuminate\Support\Str;
use Livewire\Component;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;

class B2BOperationsCenter extends Component
{
    public ?int $selectedAccountId = null;
    public ?int $contractId = null;
    public ?int $workOrderId = null;

    public array $contractForm = [
        'organization_account_id' => null,
        'country_id' => null,
        'service_zone_id' => null,
        'default_field_team_id' => null,
        'default_service_partner_id' => null,
        'contract_reference' => '',
        'status' => 'draft',
        'pricing_model' => 'catalog',
        'billing_cycle' => 'monthly',
        'effective_from' => null,
        'effective_to' => null,
        'approval_mode' => 'auto',
        'requires_purchase_order' => false,
        'default_cost_center' => '',
        'negotiated_discount_percent' => null,
        'payment_terms_days' => null,
        'sla_response_hours' => null,
        'sla_resolution_hours' => null,
        'notes' => '',
    ];

    public array $workOrderForm = [
        'organization_account_id' => null,
        'organization_site_id' => null,
        'organization_contract_id' => null,
        'service_catalog_id' => null,
        'service_zone_id' => null,
        'requested_by_user_id' => null,
        'assigned_field_team_id' => null,
        'assigned_service_partner_id' => null,
        'title' => '',
        'reference' => '',
        'status' => 'draft',
        'priority' => 'normale',
        'approval_status' => 'pending',
        'work_type' => 'site_intervention',
        'requested_start_at' => null,
        'requested_end_at' => null,
        'scheduled_start_at' => null,
        'scheduled_end_at' => null,
        'purchase_order_number' => '',
        'cost_center' => '',
        'budget_amount' => null,
        'instructions' => '',
    ];

    public array $workOrderLines = [
        ['title' => '', 'service_catalog_id' => null, 'quantity' => 1, 'unit' => 'forfait', 'unit_price' => null, 'surface_value' => null],
    ];

    protected function rules(): array
    {
        return [
            'contractForm.organization_account_id' => ['required', 'exists:organization_accounts,id'],
            'contractForm.country_id' => ['nullable', 'exists:countries,id'],
            'contractForm.service_zone_id' => ['nullable', 'exists:service_zones,id'],
            'contractForm.default_field_team_id' => ['nullable', 'exists:field_teams,id'],
            'contractForm.default_service_partner_id' => ['nullable', 'exists:service_partners,id'],
            'contractForm.contract_reference' => ['required', 'string', 'max:255'],
            'contractForm.status' => ['required', 'string', 'max:50'],
            'contractForm.pricing_model' => ['required', 'string', 'max:50'],
            'contractForm.billing_cycle' => ['required', 'string', 'max:50'],
            'contractForm.approval_mode' => ['required', 'string', 'max:50'],
            'contractForm.default_cost_center' => ['nullable', 'string', 'max:100'],
            'contractForm.negotiated_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'contractForm.payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'contractForm.sla_response_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'contractForm.sla_resolution_hours' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'contractForm.notes' => ['nullable', 'string', 'max:2000'],

            'workOrderForm.organization_account_id' => ['required', 'exists:organization_accounts,id'],
            'workOrderForm.organization_site_id' => ['nullable', 'exists:organization_sites,id'],
            'workOrderForm.organization_contract_id' => ['nullable', 'exists:organization_contracts,id'],
            'workOrderForm.service_catalog_id' => ['nullable', 'exists:service_catalogs,id'],
            'workOrderForm.service_zone_id' => ['nullable', 'exists:service_zones,id'],
            'workOrderForm.requested_by_user_id' => ['nullable', 'exists:users,id'],
            'workOrderForm.assigned_field_team_id' => ['nullable', 'exists:field_teams,id'],
            'workOrderForm.assigned_service_partner_id' => ['nullable', 'exists:service_partners,id'],
            'workOrderForm.title' => ['required', 'string', 'max:255'],
            'workOrderForm.reference' => ['required', 'string', 'max:255'],
            'workOrderForm.status' => ['required', 'string', 'max:50'],
            'workOrderForm.priority' => ['required', 'string', 'max:50'],
            'workOrderForm.approval_status' => ['required', 'string', 'max:50'],
            'workOrderForm.work_type' => ['required', 'string', 'max:50'],
            'workOrderForm.purchase_order_number' => ['nullable', 'string', 'max:100'],
            'workOrderForm.cost_center' => ['nullable', 'string', 'max:100'],
            'workOrderForm.budget_amount' => ['nullable', 'numeric', 'min:0'],
            'workOrderForm.instructions' => ['nullable', 'string', 'max:4000'],
            'workOrderLines.*.title' => ['required', 'string', 'max:255'],
            'workOrderLines.*.service_catalog_id' => ['nullable', 'exists:service_catalogs,id'],
            'workOrderLines.*.quantity' => ['nullable', 'numeric', 'min:0.1'],
            'workOrderLines.*.unit' => ['nullable', 'string', 'max:50'],
            'workOrderLines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'workOrderLines.*.surface_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function mount(): void
    {
        $this->selectedAccountId = OrganizationAccount::query()->value('id');
        $this->prefillFromSelectedAccount();
    }

    public function updatedSelectedAccountId(): void
    {
        $this->prefillFromSelectedAccount();
    }

    protected function prefillFromSelectedAccount(): void
    {
        if (! $this->selectedAccountId) {
            return;
        }

        $account = OrganizationAccount::with(['postalCodeReference.country', 'activeOrganizationContract'])->find($this->selectedAccountId);
        if (! $account) {
            return;
        }

        $countryId = $account->country_id ?: $account->postalCodeReference?->country_id;
        $this->contractForm['organization_account_id'] = $account->id;
        $this->contractForm['country_id'] = $countryId;
        $this->workOrderForm['organization_account_id'] = $account->id;
        $this->workOrderForm['requested_by_user_id'] = $account->users()->value('id');

        if ($account->activeOrganizationContract) {
            $this->loadContract($account->activeOrganizationContract->id);
            $this->workOrderForm['organization_contract_id'] = $account->activeOrganizationContract->id;
            $this->workOrderForm['service_zone_id'] = $account->activeOrganizationContract->service_zone_id;
            $this->workOrderForm['assigned_field_team_id'] = $account->activeOrganizationContract->default_field_team_id;
            $this->workOrderForm['assigned_service_partner_id'] = $account->activeOrganizationContract->default_service_partner_id;
            $this->workOrderForm['cost_center'] = $account->activeOrganizationContract->default_cost_center ?? '';
        }
    }

    public function loadContract(int $id): void
    {
        $contract = OrganizationContract::findOrFail($id);
        $this->contractId = $contract->id;
        $this->contractForm = [
            'organization_account_id' => $contract->organization_account_id,
            'country_id' => $contract->country_id,
            'service_zone_id' => $contract->service_zone_id,
            'default_field_team_id' => $contract->default_field_team_id,
            'default_service_partner_id' => $contract->default_service_partner_id,
            'contract_reference' => $contract->contract_reference,
            'status' => $contract->status,
            'pricing_model' => $contract->pricing_model,
            'billing_cycle' => $contract->billing_cycle,
            'effective_from' => optional($contract->effective_from)->format('Y-m-d'),
            'effective_to' => optional($contract->effective_to)->format('Y-m-d'),
            'approval_mode' => $contract->approval_mode,
            'requires_purchase_order' => $contract->requires_purchase_order,
            'default_cost_center' => $contract->default_cost_center,
            'negotiated_discount_percent' => $contract->negotiated_discount_percent,
            'payment_terms_days' => $contract->payment_terms_days,
            'sla_response_hours' => $contract->sla_response_hours,
            'sla_resolution_hours' => $contract->sla_resolution_hours,
            'notes' => $contract->notes,
        ];
    }

    public function saveContract(): void
    {
        $this->validateOnly('contractForm.organization_account_id');
        $this->validate([
            'contractForm.organization_account_id' => ['required', 'exists:organization_accounts,id'],
            'contractForm.contract_reference' => ['required', 'string', 'max:255'],
            'contractForm.status' => ['required', 'string', 'max:50'],
            'contractForm.pricing_model' => ['required', 'string', 'max:50'],
            'contractForm.billing_cycle' => ['required', 'string', 'max:50'],
            'contractForm.approval_mode' => ['required', 'string', 'max:50'],
        ]);

        $contract = OrganizationContract::updateOrCreate(
            ['id' => $this->contractId],
            $this->contractForm
        );

        $this->contractId = $contract->id;
        $this->workOrderForm['organization_contract_id'] = $contract->id;
        ActivityLogger::log('organization_contract_saved', $contract, [
            'account_id' => $contract->organization_account_id,
            'status' => $contract->status,
            'reference' => $contract->contract_reference,
        ]);
        $this->dispatch('toast', 'Contrat entreprise enregistré.', 'success');
    }

    public function addWorkOrderLine(): void
    {
        $this->workOrderLines[] = ['title' => '', 'service_catalog_id' => null, 'quantity' => 1, 'unit' => 'forfait', 'unit_price' => null, 'surface_value' => null];
    }

    public function removeWorkOrderLine(int $index): void
    {
        unset($this->workOrderLines[$index]);
        $this->workOrderLines = array_values($this->workOrderLines);
    }

    public function saveWorkOrder(): void
    {
        $this->validate([
            'workOrderForm.organization_account_id' => ['required', 'exists:organization_accounts,id'],
            'workOrderForm.title' => ['required', 'string', 'max:255'],
            'workOrderForm.reference' => ['required', 'string', 'max:255'],
            'workOrderForm.status' => ['required', 'string', 'max:50'],
            'workOrderForm.priority' => ['required', 'string', 'max:50'],
            'workOrderForm.approval_status' => ['required', 'string', 'max:50'],
            'workOrderForm.work_type' => ['required', 'string', 'max:50'],
            'workOrderLines.*.title' => ['required', 'string', 'max:255'],
        ]);

        $workOrder = EnterpriseWorkOrder::updateOrCreate(
            ['id' => $this->workOrderId],
            $this->workOrderForm
        );

        $this->workOrderId = $workOrder->id;
        $workOrder->lines()->delete();

        foreach ($this->workOrderLines as $line) {
            $qty = (float) ($line['quantity'] ?: 1);
            $unitPrice = $line['unit_price'] !== null && $line['unit_price'] !== '' ? (float) $line['unit_price'] : null;
            WorkOrderLine::create([
                'enterprise_work_order_id' => $workOrder->id,
                'service_catalog_id' => $line['service_catalog_id'] ?: null,
                'title' => $line['title'],
                'description' => null,
                'quantity' => $qty,
                'unit' => $line['unit'] ?: 'forfait',
                'unit_price' => $unitPrice,
                'line_total' => $unitPrice !== null ? round($qty * $unitPrice, 2) : null,
                'surface_value' => $line['surface_value'] ?: null,
            ]);
        }

        if ($workOrder->approval_status === 'pending') {
            WorkOrderApproval::firstOrCreate([
                'enterprise_work_order_id' => $workOrder->id,
                'approval_status' => 'pending',
            ], [
                'approver_user_id' => null,
            ]);
        }

        ActivityLogger::log('enterprise_work_order_saved', $workOrder, [
            'account_id' => $workOrder->organization_account_id,
            'reference' => $workOrder->reference,
            'approval_status' => $workOrder->approval_status,
            'line_count' => count($this->workOrderLines),
        ]);
        $this->dispatch('toast', 'Ordre de service enregistré.', 'success');
    }

    public function approveWorkOrder(int $id): void
    {
        $workOrder = EnterpriseWorkOrder::findOrFail($id);
        $workOrder->update([
            'approval_status' => 'approved',
            'status' => $workOrder->status === 'draft' ? 'approved' : $workOrder->status,
        ]);

        WorkOrderApproval::create([
            'enterprise_work_order_id' => $workOrder->id,
            'approver_user_id' => auth()->id(),
            'approval_status' => 'approved',
            'approved_at' => now(),
            'comment' => 'Validation depuis le centre B2B.',
        ]);

        ActivityLogger::log('enterprise_work_order_approved', $workOrder, ['reference' => $workOrder->reference]);
        $this->dispatch('toast', 'Ordre de service approuvé.', 'success');
    }

    public function rejectWorkOrder(int $id): void
    {
        $workOrder = EnterpriseWorkOrder::findOrFail($id);
        $workOrder->update([
            'approval_status' => 'rejected',
            'status' => 'blocked',
        ]);

        WorkOrderApproval::create([
            'enterprise_work_order_id' => $workOrder->id,
            'approver_user_id' => auth()->id(),
            'approval_status' => 'rejected',
            'rejected_at' => now(),
            'comment' => 'Blocage depuis le centre B2B.',
        ]);

        ActivityLogger::log('enterprise_work_order_rejected', $workOrder, ['reference' => $workOrder->reference]);
        $this->dispatch('toast', 'Ordre de service rejeté.', 'error');
    }

    public function render(): View
    {
        $accounts = OrganizationAccount::query()->withCount(['sites', 'organizationContracts', 'enterpriseWorkOrders'])->orderBy('name')->get();

        $contracts = OrganizationContract::query()
            ->with(['organizationAccount', 'defaultFieldTeam', 'defaultServicePartner'])
            ->when($this->selectedAccountId, fn ($q) => $q->where('organization_account_id', $this->selectedAccountId))
            ->latest()
            ->limit(10)
            ->get();

        $workOrders = EnterpriseWorkOrder::query()
            ->with(['organizationAccount', 'organizationSite', 'organizationContract', 'assignedFieldTeam', 'assignedServicePartner'])
            ->when($this->selectedAccountId, fn ($q) => $q->where('organization_account_id', $this->selectedAccountId))
            ->latest()
            ->limit(12)
            ->get();

        return view('livewire.admin.b2b-operations-center', [
            'accounts' => $accounts,
            'contracts' => $contracts,
            'workOrders' => $workOrders,
            'zones' => ServiceZone::orderBy('name')->get(['id', 'name']),
            'services' => ServiceCatalog::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'teams' => FieldTeam::orderBy('name')->get(['id', 'name']),
            'partners' => ServicePartner::orderBy('name')->get(['id', 'name']),
            'requesters' => User::clientFacing()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}

<?php

namespace App\Livewire\Admin;

use App\Models\ActivityLog;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\ZoneServiceRule;
use App\Support\ActivityLogger;
use App\Support\Livewire\Concerns\Admin\ManagesServiceOptions;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;

class CatalogueServices extends Component
{
    use ManagesServiceOptions;
    use WithPagination;

    public $search = '';
    public $status = '';
    public $market = '';
    public $serviceType = '';
    public $tradeFilter = '';   // Phase 1 — filtrer par métier dans la liste

    public $serviceId = null;
    public $code = '';
    public $name = '';
    public $slug = '';
    public $description = '';
    public $service_type = 'standard';
    public $is_active = true;
    public $requires_quote = false;
    public $requires_manual_validation = false;
    public $is_entreprise = false;
    public $default_duration_minutes = 60;
    public $base_price = 0;
    public $sort_order = 0;
    public ?int $trade_id = null;   // Phase 1 — métier (Trade) auquel le service est rattaché

    public $selectedServiceId = null;
    public $selectedZoneId = null;
    public $rule_enabled = true;
    public $rule_requires_manual_validation = false;
    public $rule_base_price_override = null;
    public $rule_price_multiplier = 1;
    public $rule_minimum_notice_hours = null;
    public $rule_maximum_daily_capacity = null;

    protected $queryString = ['search', 'status', 'market', 'serviceType', 'tradeFilter', 'page'];

    public function mount(): void
    {
        $this->resetServiceForm();
        $this->resetNewOption();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingMarket(): void
    {
        $this->resetPage();
    }

    public function updatingServiceType(): void
    {
        $this->resetPage();
    }

    public function updatingTradeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedName($value): void
    {
        if (! $this->serviceId) {
            $this->slug = Str::slug((string) $value);
            $this->code = Str::upper(Str::limit(Str::slug((string) $value, '_'), 20, ''));
        }
    }

    public function resetServiceForm(): void
    {
        $this->serviceId = null;
        $this->code = '';
        $this->name = '';
        $this->slug = '';
        $this->description = '';
        $this->service_type = 'standard';
        $this->is_active = true;
        $this->requires_quote = false;
        $this->requires_manual_validation = false;
        $this->is_entreprise = false;
        $this->default_duration_minutes = 60;
        $this->base_price = 0;
        $this->sort_order = 0;
        $this->trade_id = null;
    }

    public function saveService(): void
    {
        $validated = $this->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'service_type' => ['required', 'string', 'max:50'],
            'is_active' => ['boolean'],
            'requires_quote' => ['boolean'],
            'requires_manual_validation' => ['boolean'],
            'is_entreprise' => ['boolean'],
            'default_duration_minutes' => ['required', 'integer', 'min:15', 'max:1440'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            // Phase 1 — `trade_id` requis sauf si l'admin ne veut pas rattacher
            // (auquel cas on accepte null pour rester rétro-compat). À durcir
            // en `required` quand toute la base aura été backfillée.
            'trade_id' => ['nullable', 'integer', 'exists:trades,id'],
        ]);

        $duplicateRules = [
            'code' => 'unique:service_catalogs,code',
            'slug' => 'unique:service_catalogs,slug',
        ];

        if ($this->serviceId) {
            $duplicateRules['code'] .= ',' . $this->serviceId;
            $duplicateRules['slug'] .= ',' . $this->serviceId;
        }

        $this->validate([
            'code' => $duplicateRules['code'],
            'slug' => $duplicateRules['slug'],
        ]);

        $service = ServiceCatalog::updateOrCreate(
            ['id' => $this->serviceId],
            $validated
        );

        ActivityLogger::log($this->serviceId ? 'service.updated' : 'service.created', $service, [
            'code' => $service->code,
            'name' => $service->name,
            'service_type' => $service->service_type,
            'is_entreprise' => $service->is_entreprise,
            'trade_id' => $service->trade_id,
        ]);

        $this->selectedServiceId = $service->id;
        $this->serviceId = $service->id;

        session()->flash('success', 'Service enregistré.');
    }

    public function editService(int $serviceId): void
    {
        $service = ServiceCatalog::findOrFail($serviceId);

        $this->serviceId = $service->id;
        $this->selectedServiceId = $service->id;
        $this->code = $service->code;
        $this->name = $service->name;
        $this->slug = $service->slug;
        $this->description = $service->description ?? '';
        $this->service_type = $service->service_type;
        $this->is_active = (bool) $service->is_active;
        $this->requires_quote = (bool) $service->requires_quote;
        $this->requires_manual_validation = (bool) $service->requires_manual_validation;
        $this->is_entreprise = (bool) $service->is_entreprise;
        $this->default_duration_minutes = (int) $service->default_duration_minutes;
        $this->base_price = (float) $service->base_price;
        $this->sort_order = (int) $service->sort_order;
        $this->trade_id = $service->trade_id ? (int) $service->trade_id : null;
    }

    public function selectService(int $serviceId): void
    {
        $this->selectedServiceId = $serviceId;
        $this->selectedZoneId = null;
        $this->resetRuleForm();
        $this->editService($serviceId);
        $this->loadOptionsForService($serviceId);
    }

    public function toggleActive(int $serviceId): void
    {
        $service = ServiceCatalog::findOrFail($serviceId);
        $service->update(['is_active' => ! $service->is_active]);

        ActivityLogger::log('service.toggled', $service, ['is_active' => $service->is_active]);

        session()->flash('success', 'Statut du service mis à jour.');
    }

    public function resetRuleForm(): void
    {
        $this->selectedZoneId = null;
        $this->rule_enabled = true;
        $this->rule_requires_manual_validation = false;
        $this->rule_base_price_override = null;
        $this->rule_price_multiplier = 1;
        $this->rule_minimum_notice_hours = null;
        $this->rule_maximum_daily_capacity = null;
    }

    public function editZoneRule(int $zoneId): void
    {
        if (! $this->selectedServiceId) {
            return;
        }

        $this->selectedZoneId = $zoneId;

        $rule = ZoneServiceRule::where('service_catalog_id', $this->selectedServiceId)
            ->where('service_zone_id', $zoneId)
            ->first();

        $this->rule_enabled = (bool) ($rule->is_enabled ?? true);
        $this->rule_requires_manual_validation = (bool) ($rule->requires_manual_validation ?? false);
        $this->rule_base_price_override = $rule?->base_price_override;
        $this->rule_price_multiplier = (float) ($rule->price_multiplier ?? 1);
        $this->rule_minimum_notice_hours = $rule?->minimum_notice_hours;
        $this->rule_maximum_daily_capacity = $rule?->maximum_daily_capacity;
    }

    public function saveZoneRule(): void
    {
        $this->validate([
            'selectedServiceId' => ['required', 'exists:service_catalogs,id'],
            'selectedZoneId' => ['required', 'exists:service_zones,id'],
            'rule_enabled' => ['boolean'],
            'rule_requires_manual_validation' => ['boolean'],
            'rule_base_price_override' => ['nullable', 'numeric', 'min:0'],
            'rule_price_multiplier' => ['required', 'numeric', 'min:0.1', 'max:20'],
            'rule_minimum_notice_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'rule_maximum_daily_capacity' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $rule = ZoneServiceRule::updateOrCreate(
            [
                'service_catalog_id' => $this->selectedServiceId,
                'service_zone_id' => $this->selectedZoneId,
            ],
            [
                'is_enabled' => (bool) $this->rule_enabled,
                'requires_manual_validation' => (bool) $this->rule_requires_manual_validation,
                'base_price_override' => $this->rule_base_price_override,
                'price_multiplier' => $this->rule_price_multiplier,
                'minimum_notice_hours' => $this->rule_minimum_notice_hours,
                'maximum_daily_capacity' => $this->rule_maximum_daily_capacity,
            ]
        );

        ActivityLogger::log('service.zone_rule.saved', $rule, [
            'service_catalog_id' => $this->selectedServiceId,
            'service_zone_id' => $this->selectedZoneId,
            'is_enabled' => $rule->is_enabled,
        ]);

        session()->flash('success', 'Règle de zone enregistrée.');
    }

    public function render(): View
    {
        $services = ServiceCatalog::query()
            ->with('trade:id,name,slug,color')
            ->when($this->search, function ($query) {
                $query->where(function ($sub) {
                    $sub->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('code', 'like', '%' . $this->search . '%')
                        ->orWhere('slug', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->status !== '', fn ($query) => $query->where('is_active', $this->status === 'active'))
            ->when($this->market !== '', function ($query) {
                if ($this->market === 'entreprise') {
                    $query->where('is_entreprise', true);
                }

                if ($this->market === 'standard') {
                    $query->where('is_entreprise', false);
                }
            })
            ->when($this->serviceType !== '', fn ($query) => $query->where('service_type', $this->serviceType))
            ->when($this->tradeFilter !== '', fn ($query) => $query->where('trade_id', $this->tradeFilter))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(10);

        $zones = ServiceZone::query()
            ->with(['region', 'province'])
            ->orderBy('priority')
            ->orderBy('name')
            ->get();

        $selectedService = $this->selectedServiceId
            ? ServiceCatalog::with(['zoneServiceRules.serviceZone', 'trade'])->find($this->selectedServiceId)
            : null;

        $serviceTypes = ServiceCatalog::query()
            ->select('service_type')
            ->distinct()
            ->pluck('service_type')
            ->filter()
            ->values();

        // Phase 1 — liste des trades pour le <select> du formulaire et du filtre
        $trades = Trade::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'color']);

        $serviceLogs = $selectedService
            ? ActivityLog::query()
                ->where(function ($query) use ($selectedService) {
                    $query->where(function ($q) use ($selectedService) {
                        $q->where('target_type', ServiceCatalog::class)
                            ->where('target_id', $selectedService->id);
                    })->orWhere(function ($q) use ($selectedService) {
                        $q->where('action', 'service.zone_rule.saved')
                            ->where('meta->service_catalog_id', $selectedService->id);
                    });
                })
                ->latest()
                ->take(10)
                ->get()
            : collect();

        return view('livewire.admin.catalogue-services', [
            'services' => $services,
            'zones' => $zones,
            'selectedService' => $selectedService,
            'serviceTypes' => $serviceTypes,
            'trades' => $trades,
            'serviceLogs' => $serviceLogs,
        ]);
    }
}

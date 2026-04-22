<?php

namespace App\Livewire\Admin;

use App\Models\Country;
use App\Models\CountryBillingProfile;
use App\Models\CountryOperationalSetting;
use App\Models\CountryServiceCatalogRule;
use App\Models\MarketLaunchReadiness;
use App\Models\ServiceCatalog;
use App\Support\ActivityLogger;
use Livewire\Component;

class InternationalOperationsCenter extends Component
{
    public string $search = '';
    public string $stageFilter = '';
    public ?int $selectedCountryId = null;

    public bool $booking_enabled = false;
    public bool $mission_enabled = false;
    public bool $billing_enabled = false;
    public bool $partner_network_enabled = false;
    public string $readiness_stage = 'draft';
    public string $currency_symbol = '€';
    public string $date_format = 'd/m/Y';
    public string $time_format = 'H:i';
    public string $address_format = 'line1_postal_city_country';
    public string $phone_format = 'international';
    public bool $requires_vat_number_for_companies = true;
    public string $default_distance_unit = 'km';
    public string $default_surface_unit = 'm2';
    public ?float $default_tax_rate = 21.0;

    public string $invoice_prefix = 'INV';
    public string $quote_prefix = 'QUO';
    public string $tax_label = 'TVA';
    public bool $prices_include_tax = false;
    public string $rounding_mode = 'half_up';
    public string $decimal_separator = ',';
    public string $thousands_separator = ' ';
    public int $payment_terms_days = 30;

    public bool $catalog_ready = false;
    public bool $booking_ready = false;
    public bool $mission_ready = false;
    public bool $billing_ready = false;
    public bool $partner_network_ready = false;
    public bool $compliance_ready = false;
    public bool $support_ready = false;
    public string $readiness_notes = '';

    public ?int $service_catalog_id = null;
    public bool $service_is_enabled = true;
    public bool $service_requires_manual_validation = false;
    public bool $service_requires_quote = false;
    public int $service_minimum_notice_hours = 24;
    public ?int $service_sla_response_hours = null;
    public ?int $service_sla_resolution_hours = null;
    public ?float $service_pricing_multiplier = 1.0;
    public ?int $service_default_team_id = null;
    public ?int $service_default_partner_id = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'stageFilter' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->selectFirstCountry();
    }

    public function updatedSearch(): void
    {
        if (! $this->selectedCountryId) {
            $this->selectFirstCountry();
        }
    }

    public function updatedStageFilter(): void
    {
        if (! $this->selectedCountryId) {
            $this->selectFirstCountry();
        }
    }

    public function updatedSelectedCountryId($value): void
    {
        if ($value) {
            $this->selectCountry((int) $value);
        }
    }

    public function updatedServiceCatalogId($value): void
    {
        if ($this->selectedCountryId && $value) {
            $this->loadServiceRule((int) $value);
        }
    }

    public function selectFirstCountry(): void
    {
        $first = $this->countries->first();

        if ($first) {
            $this->selectCountry((int) $first->id);
        }
    }

    public function selectCountry(int $countryId): void
    {
        $country = Country::with(['operationalSetting', 'billingProfile', 'launchReadiness'])->findOrFail($countryId);
        $this->selectedCountryId = $country->id;

        $operational = $country->operationalSetting;
        $billing = $country->billingProfile;
        $readiness = $country->launchReadiness;

        $this->booking_enabled = (bool) ($operational->booking_enabled ?? false);
        $this->mission_enabled = (bool) ($operational->mission_enabled ?? false);
        $this->billing_enabled = (bool) ($operational->billing_enabled ?? false);
        $this->partner_network_enabled = (bool) ($operational->partner_network_enabled ?? false);
        $this->readiness_stage = (string) ($operational->readiness_stage ?? 'draft');
        $this->currency_symbol = (string) ($operational->currency_symbol ?? $this->guessCurrencySymbol($country->currency_code));
        $this->date_format = (string) ($operational->date_format ?? 'd/m/Y');
        $this->time_format = (string) ($operational->time_format ?? 'H:i');
        $this->address_format = (string) ($operational->address_format ?? 'line1_postal_city_country');
        $this->phone_format = (string) ($operational->phone_format ?? 'international');
        $this->requires_vat_number_for_companies = (bool) ($operational->requires_vat_number_for_companies ?? true);
        $this->default_distance_unit = (string) ($operational->default_distance_unit ?? 'km');
        $this->default_surface_unit = (string) ($operational->default_surface_unit ?? 'm2');
        $this->default_tax_rate = $operational ? (float) $operational->default_tax_rate : 21.0;

        $this->invoice_prefix = (string) ($billing->invoice_prefix ?? 'INV');
        $this->quote_prefix = (string) ($billing->quote_prefix ?? 'QUO');
        $this->tax_label = (string) ($billing->tax_label ?? 'TVA');
        $this->prices_include_tax = (bool) ($billing->prices_include_tax ?? false);
        $this->rounding_mode = (string) ($billing->rounding_mode ?? 'half_up');
        $this->decimal_separator = (string) ($billing->decimal_separator ?? ',');
        $this->thousands_separator = (string) ($billing->thousands_separator ?? ' ');
        $this->payment_terms_days = (int) ($billing->payment_terms_days ?? 30);

        $this->catalog_ready = (bool) ($readiness->catalog_ready ?? false);
        $this->booking_ready = (bool) ($readiness->booking_ready ?? false);
        $this->mission_ready = (bool) ($readiness->mission_ready ?? false);
        $this->billing_ready = (bool) ($readiness->billing_ready ?? false);
        $this->partner_network_ready = (bool) ($readiness->partner_network_ready ?? false);
        $this->compliance_ready = (bool) ($readiness->compliance_ready ?? false);
        $this->support_ready = (bool) ($readiness->support_ready ?? false);
        $this->readiness_notes = (string) ($readiness->notes ?? '');

        $this->service_catalog_id = $this->serviceCatalogs->first()?->id;
        if ($this->service_catalog_id) {
            $this->loadServiceRule($this->service_catalog_id);
        }
    }

    protected function guessCurrencySymbol(?string $currencyCode): string
    {
        return match (strtoupper((string) $currencyCode)) {
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            'CHF' => 'CHF',
            default => strtoupper((string) $currencyCode),
        };
    }

    public function loadServiceRule(int $serviceCatalogId): void
    {
        $rule = CountryServiceCatalogRule::query()
            ->where('country_id', $this->selectedCountryId)
            ->where('service_catalog_id', $serviceCatalogId)
            ->first();

        $this->service_catalog_id = $serviceCatalogId;
        $this->service_is_enabled = (bool) ($rule->is_enabled ?? true);
        $this->service_requires_manual_validation = (bool) ($rule->requires_manual_validation ?? false);
        $this->service_requires_quote = (bool) ($rule->requires_quote ?? false);
        $this->service_minimum_notice_hours = (int) ($rule->minimum_notice_hours ?? 24);
        $this->service_sla_response_hours = $rule?->sla_response_hours;
        $this->service_sla_resolution_hours = $rule?->sla_resolution_hours;
        $this->service_pricing_multiplier = $rule ? (float) $rule->pricing_multiplier : 1.0;
        $this->service_default_team_id = $rule?->default_team_id;
        $this->service_default_partner_id = $rule?->default_partner_id;
    }

    public function saveOperationalSetting(): void
    {
        $validated = $this->validate([
            'selectedCountryId' => ['required', 'exists:countries,id'],
            'readiness_stage' => ['required', 'in:draft,catalog_only,booking_enabled,mission_enabled,billing_enabled,ready_for_launch'],
            'default_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'currency_symbol' => ['required', 'string', 'max:10'],
            'date_format' => ['required', 'string', 'max:30'],
            'time_format' => ['required', 'string', 'max:30'],
            'address_format' => ['required', 'string', 'max:100'],
            'phone_format' => ['required', 'string', 'max:50'],
            'default_distance_unit' => ['required', 'string', 'max:10'],
            'default_surface_unit' => ['required', 'string', 'max:10'],
        ]);

        $setting = CountryOperationalSetting::updateOrCreate(
            ['country_id' => $validated['selectedCountryId']],
            [
                'booking_enabled' => $this->booking_enabled,
                'mission_enabled' => $this->mission_enabled,
                'billing_enabled' => $this->billing_enabled,
                'partner_network_enabled' => $this->partner_network_enabled,
                'readiness_stage' => $validated['readiness_stage'],
                'default_tax_rate' => $validated['default_tax_rate'] ?? 0,
                'currency_symbol' => $validated['currency_symbol'],
                'date_format' => $validated['date_format'],
                'time_format' => $validated['time_format'],
                'address_format' => $validated['address_format'],
                'phone_format' => $validated['phone_format'],
                'requires_vat_number_for_companies' => $this->requires_vat_number_for_companies,
                'default_distance_unit' => $validated['default_distance_unit'],
                'default_surface_unit' => $validated['default_surface_unit'],
                'local_rules' => [
                    'booking_enabled' => $this->booking_enabled,
                    'mission_enabled' => $this->mission_enabled,
                    'billing_enabled' => $this->billing_enabled,
                    'partner_network_enabled' => $this->partner_network_enabled,
                ],
            ]
        );

        ActivityLogger::log('country_operational_setting.saved', $setting, [
            'country_id' => $setting->country_id,
            'readiness_stage' => $setting->readiness_stage,
        ]);

        session()->flash('success', 'Réglages opérationnels pays enregistrés.');
    }

    public function saveBillingProfile(): void
    {
        $validated = $this->validate([
            'selectedCountryId' => ['required', 'exists:countries,id'],
            'invoice_prefix' => ['required', 'string', 'max:20'],
            'quote_prefix' => ['required', 'string', 'max:20'],
            'tax_label' => ['required', 'string', 'max:30'],
            'default_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rounding_mode' => ['required', 'string', 'max:30'],
            'decimal_separator' => ['required', 'string', 'max:5'],
            'thousands_separator' => ['required', 'string', 'max:5'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        $profile = CountryBillingProfile::updateOrCreate(
            ['country_id' => $validated['selectedCountryId']],
            [
                'invoice_prefix' => strtoupper($validated['invoice_prefix']),
                'quote_prefix' => strtoupper($validated['quote_prefix']),
                'tax_label' => $validated['tax_label'],
                'default_tax_rate' => $validated['default_tax_rate'] ?? 0,
                'prices_include_tax' => $this->prices_include_tax,
                'rounding_mode' => $validated['rounding_mode'],
                'decimal_separator' => $validated['decimal_separator'],
                'thousands_separator' => $validated['thousands_separator'],
                'payment_terms_days' => $validated['payment_terms_days'],
            ]
        );

        ActivityLogger::log('country_billing_profile.saved', $profile, [
            'country_id' => $profile->country_id,
        ]);

        session()->flash('success', 'Profil de facturation pays enregistré.');
    }

    public function saveReadiness(): void
    {
        $validated = $this->validate([
            'selectedCountryId' => ['required', 'exists:countries,id'],
            'readiness_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $readiness = MarketLaunchReadiness::updateOrCreate(
            ['country_id' => $validated['selectedCountryId']],
            [
                'catalog_ready' => $this->catalog_ready,
                'booking_ready' => $this->booking_ready,
                'mission_ready' => $this->mission_ready,
                'billing_ready' => $this->billing_ready,
                'partner_network_ready' => $this->partner_network_ready,
                'compliance_ready' => $this->compliance_ready,
                'support_ready' => $this->support_ready,
                'notes' => $validated['readiness_notes'] ?: null,
                'last_audited_at' => now(),
            ]
        );

        ActivityLogger::log('market_launch_readiness.saved', $readiness, [
            'country_id' => $readiness->country_id,
            'score' => $readiness->readiness_score,
        ]);

        session()->flash('success', 'Readiness marché enregistrée.');
    }

    public function saveServiceRule(): void
    {
        $validated = $this->validate([
            'selectedCountryId' => ['required', 'exists:countries,id'],
            'service_catalog_id' => ['required', 'exists:service_catalogs,id'],
            'service_minimum_notice_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'service_sla_response_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'service_sla_resolution_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'service_pricing_multiplier' => ['nullable', 'numeric', 'min:0.1', 'max:100'],
            'service_default_team_id' => ['nullable', 'integer', 'min:1'],
            'service_default_partner_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $rule = CountryServiceCatalogRule::updateOrCreate(
            [
                'country_id' => $validated['selectedCountryId'],
                'service_catalog_id' => $validated['service_catalog_id'],
            ],
            [
                'is_enabled' => $this->service_is_enabled,
                'requires_manual_validation' => $this->service_requires_manual_validation,
                'requires_quote' => $this->service_requires_quote,
                'minimum_notice_hours' => $validated['service_minimum_notice_hours'],
                'sla_response_hours' => $validated['service_sla_response_hours'],
                'sla_resolution_hours' => $validated['service_sla_resolution_hours'],
                'default_team_id' => $validated['service_default_team_id'],
                'default_partner_id' => $validated['service_default_partner_id'],
                'pricing_multiplier' => $validated['service_pricing_multiplier'] ?? 1,
                'settings' => [
                    'managed_from' => 'international_operations_center',
                ],
            ]
        );

        ActivityLogger::log('country_service_catalog_rule.saved', $rule, [
            'country_id' => $rule->country_id,
            'service_catalog_id' => $rule->service_catalog_id,
        ]);

        session()->flash('success', 'Règle service pays enregistrée.');
    }

    public function getCountriesProperty()
    {
        return Country::query()
            ->with(['operationalSetting', 'launchReadiness'])
            ->when($this->search !== '', function ($query) {
                $search = trim($this->search);

                $query->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', '%'.$search.'%')
                        ->orWhere('official_name', 'like', '%'.$search.'%')
                        ->orWhere('iso_code', 'like', '%'.$search.'%')
                        ->orWhere('currency_code', 'like', '%'.$search.'%');
                });
            })
            ->when($this->stageFilter !== '', function ($query) {
                $query->whereHas('operationalSetting', fn ($q) => $q->where('readiness_stage', $this->stageFilter));
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
    }

    public function getSelectedCountryProperty(): ?Country
    {
        if (! $this->selectedCountryId) {
            return null;
        }

        return Country::with([
            'operationalSetting',
            'billingProfile',
            'launchReadiness',
            'countryServiceRules.serviceCatalog',
        ])->find($this->selectedCountryId);
    }

    public function getServiceCatalogsProperty()
    {
        return ServiceCatalog::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function getSelectedCountryReadinessScoreProperty(): int
    {
        return (int) ($this->selectedCountry?->launchReadiness?->readiness_score ?? 0);
    }

    public function render()
    {
        return view('livewire.admin.international-operations-center', [
            'countries' => $this->countries,
            'selectedCountry' => $this->selectedCountry,
            'serviceCatalogs' => $this->serviceCatalogs,
            'selectedCountryReadinessScore' => $this->selectedCountryReadinessScore,
        ])->layout('layouts.app');
    }
}

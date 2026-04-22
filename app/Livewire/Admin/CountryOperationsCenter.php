<?php

namespace App\Livewire\Admin;

use App\Models\Country;
use App\Support\ActivityLogger;
use Illuminate\Validation\Rule;
use Livewire\Component;

class CountryOperationsCenter extends Component
{
    public string $search = '';
    public string $statusFilter = '';
    public ?int $selectedCountryId = null;

    public string $iso_code = '';
    public string $iso3_code = '';
    public string $name = '';
    public string $official_name = '';
    public string $default_locale = 'fr_BE';
    public string $currency_code = 'EUR';
    public string $phone_code = '';
    public string $timezone = 'Europe/Brussels';
    public bool $is_active = true;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->loadFirstCountry();
    }

    public function updatedSearch(): void
    {
        if (! $this->selectedCountryId) {
            $this->loadFirstCountry();
        }
    }

    public function updatedStatusFilter(): void
    {
        if (! $this->selectedCountryId) {
            $this->loadFirstCountry();
        }
    }

    public function loadFirstCountry(): void
    {
        $first = $this->countries->first();

        if ($first) {
            $this->selectCountry((int) $first->id);
        }
    }

    public function selectCountry(int $countryId): void
    {
        $country = Country::query()->findOrFail($countryId);

        $this->selectedCountryId = $country->id;
        $this->iso_code = (string) $country->iso_code;
        $this->iso3_code = (string) ($country->iso3_code ?? '');
        $this->name = (string) $country->name;
        $this->official_name = (string) ($country->official_name ?? '');
        $this->default_locale = (string) $country->default_locale;
        $this->currency_code = (string) $country->currency_code;
        $this->phone_code = (string) ($country->phone_code ?? '');
        $this->timezone = (string) $country->timezone;
        $this->is_active = (bool) $country->is_active;
    }

    public function saveCountry(): void
    {
        $validated = $this->validate([
            'selectedCountryId' => ['required', 'integer', 'exists:countries,id'],
            'iso_code' => [
                'required',
                'string',
                'size:2',
                Rule::unique('countries', 'iso_code')->ignore($this->selectedCountryId),
            ],
            'iso3_code' => [
                'nullable',
                'string',
                'size:3',
                Rule::unique('countries', 'iso3_code')->ignore($this->selectedCountryId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'official_name' => ['nullable', 'string', 'max:255'],
            'default_locale' => ['required', 'string', 'max:10'],
            'currency_code' => ['required', 'string', 'size:3'],
            'phone_code' => ['nullable', 'string', 'max:10'],
            'timezone' => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        $country = Country::query()->findOrFail($validated['selectedCountryId']);
        $before = [
            'iso_code' => $country->iso_code,
            'name' => $country->name,
            'default_locale' => $country->default_locale,
            'currency_code' => $country->currency_code,
            'timezone' => $country->timezone,
            'is_active' => $country->is_active,
        ];

        $country->update([
            'iso_code' => strtoupper($validated['iso_code']),
            'iso3_code' => filled($validated['iso3_code']) ? strtoupper($validated['iso3_code']) : null,
            'name' => $validated['name'],
            'official_name' => $validated['official_name'] ?: null,
            'default_locale' => $validated['default_locale'],
            'currency_code' => strtoupper($validated['currency_code']),
            'phone_code' => $validated['phone_code'] ?: null,
            'timezone' => $validated['timezone'],
            'is_active' => $this->is_active,
        ]);

        ActivityLogger::log('country.updated', $country, [
            'before' => $before,
            'after' => [
                'iso_code' => $country->iso_code,
                'name' => $country->name,
                'default_locale' => $country->default_locale,
                'currency_code' => $country->currency_code,
                'timezone' => $country->timezone,
                'is_active' => $country->is_active,
            ],
        ]);

        session()->flash('success', 'Pays enregistré avec succès.');
        $this->selectCountry($country->id);
    }

    public function toggleCountryStatus(int $countryId): void
    {
        $country = Country::query()->findOrFail($countryId);
        $country->update(['is_active' => ! $country->is_active]);

        ActivityLogger::log('country.toggled', $country, [
            'is_active' => $country->is_active,
        ]);

        if ($this->selectedCountryId === $country->id) {
            $this->selectCountry($country->id);
        }

        session()->flash('success', $country->is_active
            ? 'Pays activé.'
            : 'Pays désactivé.');
    }

    public function getCountriesProperty()
    {
        return Country::query()
            ->withCount(['regions', 'provinces', 'communes', 'postalCodes', 'serviceZones'])
            ->when($this->search !== '', function ($query) {
                $search = trim($this->search);

                $query->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', '%'.$search.'%')
                        ->orWhere('official_name', 'like', '%'.$search.'%')
                        ->orWhere('iso_code', 'like', '%'.$search.'%')
                        ->orWhere('iso3_code', 'like', '%'.$search.'%')
                        ->orWhere('currency_code', 'like', '%'.$search.'%')
                        ->orWhere('default_locale', 'like', '%'.$search.'%');
                });
            })
            ->when($this->statusFilter !== '', function ($query) {
                $query->where('is_active', $this->statusFilter === 'active');
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

        return Country::query()
            ->withCount(['regions', 'provinces', 'communes', 'postalCodes', 'serviceZones'])
            ->find($this->selectedCountryId);
    }

    public function getCountryStatsProperty(): array
    {
        return [
            'total' => Country::query()->count(),
            'active' => Country::query()->where('is_active', true)->count(),
            'inactive' => Country::query()->where('is_active', false)->count(),
            'postal_codes' => Country::query()->withCount('postalCodes')->get()->sum('postal_codes_count'),
            'service_zones' => Country::query()->withCount('serviceZones')->get()->sum('service_zones_count'),
        ];
    }

    public function render()
    {
        return view('livewire.admin.country-operations-center', [
            'countries' => $this->countries,
            'selectedCountry' => $this->selectedCountry,
            'countryStats' => $this->countryStats,
        ]);
    }
}

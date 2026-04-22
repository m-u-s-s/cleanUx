<?php

namespace App\Livewire\Client;

use App\Models\ActivityLog;
use App\Models\OrganizationSite;
use App\Models\PostalCode;
use App\Models\RendezVous;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Support\Domain\BookingStatus;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ProfilClient extends Component
{
    public ?int $editingSiteId = null;

    public string $site_name = '';
    public string $site_code = '';
    public string $contact_name = '';
    public string $site_email = '';
    public string $site_phone = '';
    public string $site_address_line_1 = '';
    public string $site_address_line_2 = '';
    public string $site_city = '';
    public string $site_postal_code = '';
    public string $access_instructions = '';
    public bool $is_primary = false;
    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'site_name' => ['required', 'string', 'max:255'],
            'site_code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('organization_sites', 'site_code')->ignore($this->editingSiteId)
                    ->where(fn ($query) => $query->where('organization_account_id', $this->organizationAccount?->id)),
            ],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'site_email' => ['nullable', 'email', 'max:255'],
            'site_phone' => ['nullable', 'string', 'max:30'],
            'site_address_line_1' => ['required', 'string', 'max:255'],
            'site_address_line_2' => ['nullable', 'string', 'max:255'],
            'site_city' => ['required', 'string', 'max:255'],
            'site_postal_code' => ['required', 'string', 'max:20'],
            'access_instructions' => ['nullable', 'string', 'max:2000'],
            'is_primary' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    public function getClientProperty()
    {
        return Auth::user();
    }

    public function getOrganizationAccountProperty()
    {
        return $this->client?->organizationAccount;
    }

    public function getIsEntrepriseProperty(): bool
    {
        return (bool) $this->client?->isEntreprise();
    }

    public function getStatsProperty()
    {
        $rdvs = RendezVous::where('client_id', Auth::id())->get();

        return [
            'total' => $rdvs->count(),
            'termine' => $rdvs->where('status', BookingStatus::TERMINE)->count(),
            'avenir' => $rdvs->whereIn('status', BookingStatus::active())->count(),
            'urgentes' => $rdvs->where('priorite', 'urgente')->count(),
        ];
    }

    public function getAdressesRecentesProperty()
    {
        return RendezVous::query()
            ->where('client_id', Auth::id())
            ->whereNotNull('adresse')
            ->where('adresse', '!=', '')
            ->leftJoin('postal_codes', 'postal_codes.id', '=', 'rendez_vous.postal_code_id')
            ->selectRaw("rendez_vous.adresse, rendez_vous.ville, COALESCE(rendez_vous.code_postal, postal_codes.code) as code_postal, MAX(rendez_vous.date) as last_date")
            ->groupBy('rendez_vous.adresse', 'rendez_vous.ville', DB::raw('COALESCE(rendez_vous.code_postal, postal_codes.code)'))
            ->orderByDesc('last_date')
            ->limit(5)
            ->get();
    }

    public function getDernierePreferenceProperty()
    {
        return RendezVous::with(['serviceCatalog', 'serviceZone', 'postalCode'])
            ->where('client_id', Auth::id())
            ->latest('date')
            ->latest('heure')
            ->first();
    }

    public function getSitesProperty()
    {
        if (! $this->isEntreprise || ! $this->organizationAccount) {
            return collect();
        }

        return OrganizationSite::with(['serviceZone', 'postalCodeReference'])
            ->where('organization_account_id', $this->organizationAccount->id)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    public function getRecentActivitiesProperty()
    {
        return ActivityLog::query()
            ->where('user_id', Auth::id())
            ->latest()
            ->limit(8)
            ->get();
    }

    public function createSite(): void
    {
        $this->resetSiteForm();
        $this->editingSiteId = 0;
    }

    public function editSite(int $siteId): void
    {
        $site = $this->sites->firstWhere('id', $siteId);

        if (! $site) {
            return;
        }

        $this->editingSiteId = $site->id;
        $this->site_name = (string) $site->name;
        $this->site_code = (string) ($site->site_code ?? '');
        $this->contact_name = (string) ($site->contact_name ?? '');
        $this->site_email = (string) ($site->email ?? '');
        $this->site_phone = (string) ($site->phone ?? '');
        $this->site_address_line_1 = (string) ($site->address_line_1 ?? '');
        $this->site_address_line_2 = (string) ($site->address_line_2 ?? '');
        $this->site_city = (string) ($site->city ?? '');
        $this->site_postal_code = (string) ($site->postal_code ?? '');
        $this->access_instructions = (string) ($site->access_instructions ?? '');
        $this->is_primary = (bool) $site->is_primary;
        $this->is_active = (bool) $site->is_active;
    }

    public function cancelSiteForm(): void
    {
        $this->resetSiteForm();
    }

    public function saveSite(): void
    {
        if (! $this->isEntreprise || ! $this->organizationAccount) {
            return;
        }

        $validated = $this->validate();
        $postalReference = $this->resolvePostalReference($validated['site_postal_code'], $validated['site_city']);
        $serviceZone = $postalReference ? $this->resolveZoneForPostalReference($postalReference) : null;

        if ($this->is_primary) {
            OrganizationSite::where('organization_account_id', $this->organizationAccount->id)
                ->update(['is_primary' => false]);
        }

        $site = OrganizationSite::updateOrCreate(
            ['id' => $this->editingSiteId ?: null],
            [
                'organization_account_id' => $this->organizationAccount->id,
                'client_user_id' => $this->client->id,
                'service_zone_id' => $serviceZone?->id,
                'postal_code_id' => $postalReference?->id,
                'name' => $validated['site_name'],
                'site_code' => $validated['site_code'] ?: null,
                'contact_name' => $validated['contact_name'] ?: null,
                'email' => $validated['site_email'] ?: null,
                'phone' => $validated['site_phone'] ?: null,
                'address_line_1' => $validated['site_address_line_1'],
                'address_line_2' => $validated['site_address_line_2'] ?: null,
                'city' => $validated['site_city'],
                'postal_code' => $validated['site_postal_code'],
                'access_instructions' => $validated['access_instructions'] ?: null,
                'is_primary' => $validated['is_primary'],
                'is_active' => $validated['is_active'],
            ]
        );

        ActivityLogger::log($this->editingSiteId ? 'organization_site_updated' : 'organization_site_created', $site, [
            'organization_account_id' => $this->organizationAccount->id,
            'service_zone_id' => $serviceZone?->id,
            'postal_code_id' => $postalReference?->id,
        ]);

        $this->resetSiteForm();
        $this->dispatch('toast', 'Site enregistré.', 'success');
    }

    public function deleteSite(int $siteId): void
    {
        $site = $this->sites->firstWhere('id', $siteId);

        if (! $site) {
            return;
        }

        ActivityLogger::log('organization_site_deleted', $site, [
            'name' => $site->name,
            'postal_code' => $site->postal_code,
        ]);

        $site->delete();
        $this->dispatch('toast', 'Site supprimé.', 'success');
    }

    protected function resetSiteForm(): void
    {
        $this->reset([
            'editingSiteId',
            'site_name',
            'site_code',
            'contact_name',
            'site_email',
            'site_phone',
            'site_address_line_1',
            'site_address_line_2',
            'site_city',
            'site_postal_code',
            'access_instructions',
            'is_primary',
        ]);

        $this->is_active = true;
    }

    protected function resolvePostalReference(?string $code, ?string $city): ?PostalCode
    {
        return app(\App\Services\Booking\ZoneCoverageService::class)->resolvePostalCode($code, $city);
    }

    protected function resolveZoneForPostalReference(PostalCode $postalCode): ?ServiceZone
    {
        $fromPostal = $postalCode->serviceZones()
            ->where('status', 'active')
            ->where('is_visible', true)
            ->orderByDesc('priority')
            ->first();

        if ($fromPostal) {
            return $fromPostal;
        }

        return ServiceZone::query()
            ->where('status', 'active')
            ->where('is_visible', true)
            ->where(function ($query) use ($postalCode) {
                $query->where(function ($provinceQuery) use ($postalCode) {
                    $provinceQuery->where('coverage_type', 'province')
                        ->where('province_id', $postalCode->province_id);
                })->orWhere(function ($regionQuery) use ($postalCode) {
                    $regionQuery->where('coverage_type', 'region')
                        ->where('region_id', $postalCode->region_id);
                })->orWhere('coverage_type', 'national');
            })
            ->orderByDesc('priority')
            ->first();
    }

    public function availableServicesForSite(int $siteId): array
    {
        $site = $this->sites->firstWhere('id', $siteId);

        if (! $site?->service_zone_id) {
            return [];
        }

        return ServiceCatalog::query()
            ->where('is_active', true)
            ->whereHas('zoneServiceRules', function ($query) use ($site) {
                $query->where('service_zone_id', $site->service_zone_id)
                    ->where('is_enabled', true);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name')
            ->take(5)
            ->values()
            ->all();
    }

    public function render()
    {
        return view('livewire.client.profil-client', [
            'client' => $this->client,
            'stats' => $this->stats,
            'adressesRecentes' => $this->adressesRecentes,
            'dernierePreference' => $this->dernierePreference,
            'organizationAccount' => $this->organizationAccount,
            'isEntreprise' => $this->isEntreprise,
            'sites' => $this->sites,
            'recentActivities' => $this->recentActivities,
        ])->layout('layouts.app');
    }
}

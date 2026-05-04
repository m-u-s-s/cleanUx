<?php

namespace App\Livewire\ClientCompany;

use App\Models\OrganizationSite;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SiteManager extends Component
{
    // ──────────────────────────────────────────────────────
    // State
    // ──────────────────────────────────────────────────────
    public bool   $showForm    = false;
    public ?int   $editingId   = null;
    public string $searchQuery = '';

    // Formulaire
    public string  $name               = '';
    public string  $address            = '';
    public string  $city               = '';
    public string  $postalCode         = '';
    public string  $country            = 'BE';
    public ?int    $surfaceM2          = null;
    public ?int    $floorCount         = null;
    public string  $accessInstructions = '';
    public string  $contactName        = '';
    public string  $contactPhone       = '';
    public string  $contactEmail       = '';
    public ?int    $preferredProviderId = null;
    public string  $cleaningFrequency  = OrganizationSite::FREQ_ONE_TIME;
    public string  $preferredTimeSlot  = '';
    public string  $notes              = '';

    // ──────────────────────────────────────────────────────
    // Mount
    // ──────────────────────────────────────────────────────
    public function mount(): void
    {
        $user = Auth::user();
        abort_unless(
            app(PermissionService::class)->can($user, 'sites.view_all', $user->currentOrganization),
            403
        );
    }

    // ──────────────────────────────────────────────────────
    // Computed
    // ──────────────────────────────────────────────────────
    public function getSitesProperty()
    {
        $orgId = Auth::user()->current_organization_id;

        return OrganizationSite::forOrg($orgId)
            ->when($this->searchQuery, fn ($q) =>
                $q->where(function ($q) {
                    $q->where('name', 'like', "%{$this->searchQuery}%")
                      ->orWhere('address', 'like', "%{$this->searchQuery}%")
                      ->orWhere('city', 'like', "%{$this->searchQuery}%");
                })
            )
            ->withCount(['bookings as active_bookings_count' => fn ($q) =>
                $q->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ])
            ->with('preferredProvider:id,name,profile_photo_path')
            ->latest()
            ->get();
    }

    public function getProvidersProperty()
    {
        return ProviderProfile::where('status', 'active')
            ->where('verification_status', 'verified')
            ->with('user:id,name,profile_photo_path')
            ->get();
    }

    // ──────────────────────────────────────────────────────
    // CRUD
    // ──────────────────────────────────────────────────────
    public function openCreate(): void
    {
        $user = Auth::user();
        abort_unless(
            app(PermissionService::class)->can($user, 'sites.create', $user->currentOrganization),
            403
        );

        $this->resetForm();
        $this->editingId = null;
        $this->showForm  = true;
    }

    public function openEdit(int $siteId): void
    {
        $user = Auth::user();
        abort_unless(
            app(PermissionService::class)->can($user, 'sites.edit', $user->currentOrganization),
            403
        );

        $site = OrganizationSite::forOrg($user->current_organization_id)->findOrFail($siteId);

        $this->editingId          = $siteId;
        $this->name               = $site->name;
        $this->address            = $site->address ?? '';
        $this->city               = $site->city ?? '';
        $this->postalCode         = $site->postal_code ?? '';
        $this->country            = $site->country ?? 'BE';
        $this->surfaceM2          = $site->surface_m2;
        $this->floorCount         = $site->floor_count;
        $this->accessInstructions = $site->access_instructions ?? '';
        $this->contactName        = $site->contact_name ?? '';
        $this->contactPhone       = $site->contact_phone ?? '';
        $this->contactEmail       = $site->contact_email ?? '';
        $this->preferredProviderId = $site->preferred_provider_id;
        $this->cleaningFrequency  = $site->cleaning_frequency ?? OrganizationSite::FREQ_ONE_TIME;
        $this->preferredTimeSlot  = $site->preferred_time_slot ?? '';
        $this->notes              = $site->notes ?? '';
        $this->showForm           = true;
    }

    public function saveSite(): void
    {
        $this->validate([
            'name'               => ['required', 'string', 'max:200'],
            'address'            => ['required', 'string', 'max:255'],
            'city'               => ['required', 'string', 'max:100'],
            'postalCode'         => ['required', 'string', 'max:20'],
            'country'            => ['required', 'string', 'size:2'],
            'surfaceM2'          => ['nullable', 'integer', 'min:1'],
            'floorCount'         => ['nullable', 'integer', 'min:1'],
            'contactEmail'       => ['nullable', 'email'],
            'cleaningFrequency'  => ['required'],
        ]);

        $user  = Auth::user();
        $orgId = $user->current_organization_id;

        $data = [
            'organization_account_id' => $orgId,
            'name'                    => $this->name,
            'address'                 => $this->address,
            'city'                    => $this->city,
            'postal_code'             => $this->postalCode,
            'country'                 => $this->country,
            'surface_m2'              => $this->surfaceM2,
            'floor_count'             => $this->floorCount,
            'access_instructions'     => $this->accessInstructions,
            'contact_name'            => $this->contactName,
            'contact_phone'           => $this->contactPhone,
            'contact_email'           => $this->contactEmail,
            'preferred_provider_id'   => $this->preferredProviderId,
            'cleaning_frequency'      => $this->cleaningFrequency,
            'preferred_time_slot'     => $this->preferredTimeSlot,
            'notes'                   => $this->notes,
            'status'                  => 'active',
        ];

        if ($this->editingId) {
            OrganizationSite::forOrg($orgId)->findOrFail($this->editingId)->update($data);
        } else {
            OrganizationSite::create($data);
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function deleteSite(int $siteId): void
    {
        $user = Auth::user();
        abort_unless(
            app(PermissionService::class)->can($user, 'sites.delete', $user->currentOrganization),
            403
        );

        OrganizationSite::forOrg($user->current_organization_id)->findOrFail($siteId)->update(['status' => 'archived']);
    }

    private function resetForm(): void
    {
        $this->name = $this->address = $this->city = $this->postalCode = '';
        $this->country = 'BE';
        $this->surfaceM2 = $this->floorCount = $this->preferredProviderId = null;
        $this->accessInstructions = $this->contactName = $this->contactPhone = '';
        $this->contactEmail = $this->preferredTimeSlot = $this->notes = '';
        $this->cleaningFrequency = OrganizationSite::FREQ_ONE_TIME;
        $this->editingId = null;
    }

    // ──────────────────────────────────────────────────────
    // Render
    // ──────────────────────────────────────────────────────
    public function render()
    {
        return view('livewire.client-company.site-manager', [
            'sites'     => $this->sitesProperty,
            'providers' => $this->providersProperty,
        ])->layout('layouts.client-company');
    }
}

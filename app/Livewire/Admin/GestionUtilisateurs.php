<?php

namespace App\Livewire\Admin;

use App\Models\ServiceZone;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Livewire\Concerns\Admin\ManagesEmployeeTrades;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class GestionUtilisateurs extends Component
{
    use ManagesEmployeeTrades;
    use WithPagination;

    public string $roleFilter = '';
    public string $search = '';
    public string $accessScopeFilter = '';
    public int $perPage = 10;

    public ?int $editingUserId = null;
    public string $securityAccessScope = User::ACCESS_SCOPE_ALL;
    public ?int $securityManagedZoneId = null;
    public array $securityPermissions = [];

    protected function currentAdmin(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingAccessScopeFilter(): void
    {
        $this->resetPage();
    }

    public function toggleActivation(int $userId): void
    {
        $user = User::findOrFail($userId);
        Gate::authorize('toggleActivation', $user);

        $nextActive = ! (bool) $user->is_active;

        $user->update([
            'is_active' => $nextActive,
            'status' => $nextActive ? 'active' : 'inactive',
        ]);

        ActivityLogger::critical('security.user_activation_toggled', $user, [
            'domain' => 'security',
            'is_active' => $nextActive,
        ]);

        session()->flash('success', 'Statut utilisateur mis à jour.');
    }

    public function updateRole(int $userId, string $newRole): void
    {
        $normalizedRole = $newRole === 'entreprise' ? User::ROLE_ENTREPRISE : $newRole;
        $user = User::findOrFail($userId);

        Gate::authorize('updateRole', $user);

        $user->update([
            'role' => $normalizedRole,
        ]);

        ActivityLogger::critical('security.user_role_updated', $user, [
            'domain' => 'security',
            'new_role' => $normalizedRole,
        ]);

        session()->flash('success', 'Rôle mis à jour.');
    }

    public function editSecurity(int $userId): void
    {
        $user = User::findOrFail($userId);
        Gate::authorize('updateAdminSecurity', $user);

        $this->editingUserId = $user->id;
        $this->securityAccessScope = $user->access_scope ?: User::ACCESS_SCOPE_ALL;
        $this->securityManagedZoneId = $user->managed_service_zone_id;
        $this->securityPermissions = $user->permissionList();
    }

    public function cancelSecurityEdit(): void
    {
        $this->editingUserId = null;
        $this->securityAccessScope = User::ACCESS_SCOPE_ALL;
        $this->securityManagedZoneId = null;
        $this->securityPermissions = [];
    }

    public function saveSecurity(): void
    {
        $user = User::findOrFail((int) $this->editingUserId);
        Gate::authorize('updateAdminSecurity', $user);

        $validated = $this->validate([
            'securityAccessScope' => [
                'required',
                'in:' . implode(',', [
                    User::ACCESS_SCOPE_ALL,
                    User::ACCESS_SCOPE_ZONE,
                    User::ACCESS_SCOPE_READONLY,
                ]),
            ],
            'securityManagedZoneId' => ['nullable', 'exists:service_zones,id'],
            'securityPermissions' => ['array'],
            'securityPermissions.*' => [
                'string',
                'in:' . implode(',', array_keys(User::allowedAdminPermissions())),
            ],
        ]);

        if (
            $validated['securityAccessScope'] === User::ACCESS_SCOPE_ZONE
            && empty($validated['securityManagedZoneId'])
        ) {
            $this->addError('securityManagedZoneId', 'Une zone est obligatoire pour un admin scope zone.');
            return;
        }

        $permissions = array_values(array_unique(array_filter($validated['securityPermissions'] ?? [])));

        $user->update([
            'access_scope' => $validated['securityAccessScope'],
            'managed_service_zone_id' => $validated['securityAccessScope'] === User::ACCESS_SCOPE_ZONE
                ? $validated['securityManagedZoneId']
                : null,
            'permissions' => $permissions,
        ]);

        ActivityLogger::critical('security.admin_security_updated', $user, [
            'domain' => 'security',
            'access_scope' => $user->access_scope,
            'managed_service_zone_id' => $user->managed_service_zone_id,
            'permissions' => $permissions,
        ]);

        session()->flash('success', 'Sécurité admin mise à jour.');
        $this->cancelSecurityEdit();
    }

    public function getPermissionOptionsProperty(): array
    {
        return User::allowedAdminPermissions();
    }

    public function getZonesProperty(): Collection
    {
        $admin = $this->currentAdmin();

        return ServiceZone::query()
            ->when($admin?->isZoneScopedAdmin(), function (Builder $query) use ($admin) {
                $query->whereKey($admin->managed_service_zone_id);
            })
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    protected function applyZoneScope(Builder $query): void
    {
        $admin = $this->currentAdmin();

        if (! $admin?->isZoneScopedAdmin()) {
            return;
        }

        $zoneId = (int) $admin->managed_service_zone_id;
        $adminId = (int) $admin->id;

        $query->where(function (Builder $scoped) use ($zoneId, $adminId) {
            $scoped->whereKey($adminId)
                ->orWhere('managed_service_zone_id', $zoneId)
                ->orWhere('primary_service_zone_id', $zoneId)
                ->orWhereHas('zoneAssignments', function (Builder $assignment) use ($zoneId) {
                    $assignment->where('service_zone_id', $zoneId)
                        ->where('is_active', true);
                })
                ->orWhereHas('organizationSites', function (Builder $site) use ($zoneId) {
                    $site->where('service_zone_id', $zoneId);
                })
                ->orWhereHas('rendezVousClient', function (Builder $rdv) use ($zoneId) {
                    $rdv->where('service_zone_id', $zoneId);
                })
                ->orWhereHas('rendezVousEmploye', function (Builder $rdv) use ($zoneId) {
                    $rdv->where('service_zone_id', $zoneId);
                });
        });
    }

    public function render(): View
    {
        $query = User::query()->with(['primaryServiceZone', 'managedServiceZone']);

        $this->applyZoneScope($query);

        $users = $query
            ->when($this->roleFilter, function (Builder $query) {
                if ($this->roleFilter === User::ROLE_CLIENT) {
                    $query->whereIn('role', User::clientRoleValues());
                    return;
                }

                $query->where('role', $this->roleFilter);
            })
            ->when($this->accessScopeFilter !== '', function (Builder $query) {
                if ($this->accessScopeFilter === 'none') {
                    $query->whereNull('access_scope');
                    return;
                }

                $query->where('access_scope', $this->accessScopeFilter);
            })
            ->when($this->search, function (Builder $query) {
                $term = '%' . $this->search . '%';

                $query->where(function (Builder $sub) use ($term) {
                    $sub->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('tva_number', 'like', $term);
                });
            })
            ->orderBy('name')
            ->paginate($this->perPage);

        return view('livewire.admin.gestion-utilisateurs', [
            'users' => $users,
            'zones' => $this->zones,
            'permissionOptions' => $this->permissionOptions,
            'allAvailableTrades' => $this->allAvailableTrades,
        ]);
    }
}
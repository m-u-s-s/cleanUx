<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Livewire\Concerns\Admin\ManagesEmployeeTrades;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class GestionUtilisateurs extends Component
{
    use EnforcesAdminAccess;
    use ManagesEmployeeTrades;
    use WithPagination;

    public string $roleFilter = '';

    public string $search = '';

    public string $accessScopeFilter = '';

    public int $perPage = 10;

    /*
     * L'EDITEUR DE SECURITE A QUITTE CET ECRAN, ET CE N'EST PAS UNE PERTE.
     *
     * Il vivait ici sans qu'aucune Blade ne l'appelle — donc atteignable seulement par
     * `/livewire/update`, qui n'a besoin d'aucun bouton. Deux portes sur la meme serrure,
     * et une seule des deux refusait qu'on accorde une capacite qu'on n'a pas soi-meme.
     *
     * Tout est porte par `/admin/roles-et-permissions` : capacites, perimetre, zone geree,
     * et les regles d'elevation qui manquaient ici.
     */
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

        // `role` n'est plus assignable en masse : c'est une colonne d'élévation, et l'inscription
        // publique passait `$request->all()` à `CreateNewUser`. Ici l'écriture est intentionnelle
        // et gardée par `Gate::authorize` juste au-dessus — d'où le `forceFill`.
        $user->forceFill([
            'role' => $normalizedRole,
        ])->save();

        ActivityLogger::critical('security.user_role_updated', $user, [
            'domain' => 'security',
            'new_role' => $normalizedRole,
        ]);

        session()->flash('success', 'Rôle mis à jour.');
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
                $term = '%'.$this->search.'%';

                $query->where(function (Builder $sub) use ($term) {
                    $sub->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('tva_number', 'like', $term);
                });
            })
            ->orderBy('name')
            ->paginate($this->perPage);

        // `zones` ET `permissionOptions` PARTENT AVEC L'EDITEUR : `render()` les passait a une
        // vue qui n'en lisait aucune. Elles servaient le formulaire de securite, qui vit
        // desormais sur `/admin/roles-et-permissions`.
        return view('livewire.admin.gestion-utilisateurs', [
            'users' => $users,
            'allAvailableTrades' => $this->allAvailableTrades,
        ]);
    }
}

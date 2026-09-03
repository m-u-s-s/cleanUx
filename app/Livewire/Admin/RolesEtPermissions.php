<?php

namespace App\Livewire\Admin;

use App\Models\AdminRole;
use App\Models\ServiceZone;
use App\Models\User;
use App\Services\Admin\GestionDesRolesService;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * RÔLES ET PERMISSIONS D'ADMINISTRATION.
 *
 * AUCUN ÉCRAN NE SAVAIT DONNER UNE CAPACITÉ. `GestionUtilisateurs` en portait les méthodes —
 * `editSecurity`, `saveSecurity`, `permissionOptions` — mais aucune Blade ne les appelait : les
 * vingt et une capacités ne se posaient qu'en base ou par un seeder.
 *
 * TROIS LECTURES SUR LA MÊME MATIÈRE, parce que trois questions se posent vraiment :
 *   — quels rôles existe-t-il, et qu'ouvrent-ils ?
 *   — que peut CETTE personne ?
 *   — qui peut toucher à l'argent ? (la lecture inverse, par capacité)
 *
 * La dernière n'existait nulle part : il fallait ouvrir les comptes un par un.
 *
 * @property-read Collection<int, AdminRole> $roles
 * @property-read Collection<int, User> $administrateurs
 */
#[Layout('layouts.app')]
class RolesEtPermissions extends Component
{
    use EnforcesAdminAccess;

    public string $onglet = 'roles';

    public ?string $message = null;

    public ?string $erreur = null;

    // ── Le rôle en cours d'édition ─────────────────────────────────────────
    #[Locked]
    public ?int $roleEnEdition = null;

    public string $nomDuRole = '';

    /** @var list<string> */
    public array $capacitesDuRole = [];

    public string $perimetreDuRole = '';

    // ── L'administrateur en cours d'édition ────────────────────────────────
    #[Locked]
    public ?int $adminEnEdition = null;

    public ?int $roleAssigne = null;

    /** @var list<string> */
    public array $capacitesEnPlus = [];

    public string $perimetre = User::ACCESS_SCOPE_ALL;

    public ?int $zoneGeree = null;

    public function boot(): void
    {
        // LA MEME CONDITION QUE LA CASE DU REGISTRE, et a CHAQUE requete : `/livewire/update`
        // ne rejoue aucun middleware de route.
        abort_unless(auth()->user()?->canAccessAdminModule('manage-users') === true, 403);
    }

    /** @return array<string, string> */
    #[Computed]
    public function capacites(): array
    {
        return app(GestionDesRolesService::class)->capacitesConnues();
    }

    /**
     * CE QUE L'ACTEUR PEUT DONNER — le reste s'affiche, mais grisé.
     *
     * @return list<string>
     */
    #[Computed]
    public function capacitesAccordables(): array
    {
        return app(GestionDesRolesService::class)->capacitesDe(auth()->user());
    }

    /** @return Collection<int, AdminRole> */
    #[Computed]
    public function roles(): Collection
    {
        return AdminRole::query()->withCount('utilisateurs')->orderBy('name')->get();
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function administrateurs(): Collection
    {
        return User::query()
            ->with('adminRole')
            ->whereIn('platform_role', [User::PLATFORM_ADMIN, User::PLATFORM_SUPER_ADMIN])
            ->orderByDesc('platform_role')
            ->orderBy('name')
            ->get();
    }

    /**
     * LA LECTURE INVERSE : pour une capacité, qui la détient et ce qu'elle ouvre.
     *
     * « Qui peut toucher à l'argent ? » n'avait aucune réponse : il fallait ouvrir les comptes un
     * par un, et deviner ce que chaque case débloque.
     *
     * @return array<string, array{libelle: string, porteurs: list<string>, ecrans: list<string>}>
     */
    #[Computed]
    public function parCapacite(): array
    {
        $ecrans = [];

        foreach ((array) config('modules.catalogue', []) as $module) {
            if (($module['gate'] ?? null) === null || ($module['context'] ?? null) !== 'admin') {
                continue;
            }

            $ecrans[$module['gate']][] = (string) $module['label'];
        }

        $tableau = [];

        foreach ($this->capacites() as $cle => $libelle) {
            $porteurs = $this->administrateurs
                ->filter(fn (User $a): bool => $a->isSuperAdmin() || in_array($cle, $a->permissionList(), true))
                ->map(fn (User $a): string => $a->name.($a->isSuperAdmin() ? ' (siège)' : ''))
                ->values()
                ->all();

            $tableau[$cle] = [
                'libelle' => $libelle,
                'porteurs' => $porteurs,
                'ecrans' => $ecrans[$cle] ?? [],
            ];
        }

        return $tableau;
    }

    /**
     * LES ZONES QUE L'ACTEUR PEUT DESIGNER.
     *
     * Un administrateur limite a une zone ne doit pas pouvoir en placer un autre sur une
     * zone voisine : ce serait sortir de son perimetre en passant par quelqu'un d'autre.
     *
     * @return Collection<int, ServiceZone>
     */
    #[Computed]
    public function zones(): Collection
    {
        $acteur = auth()->user();

        return ServiceZone::query()
            ->when(
                $acteur?->isZoneScopedAdmin(),
                fn (Builder $q) => $q->whereKey($acteur?->managed_service_zone_id),
            )
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    // ── Les rôles ──────────────────────────────────────────────────────────

    public function nouveauRole(): void
    {
        $this->reset(['roleEnEdition', 'nomDuRole', 'capacitesDuRole', 'perimetreDuRole']);
        $this->onglet = 'roles';
    }

    public function editerLeRole(int $roleId): void
    {
        $role = AdminRole::findOrFail($roleId);

        $this->roleEnEdition = $role->id;
        $this->nomDuRole = (string) $role->name;
        $this->capacitesDuRole = $role->capacites();
        $this->perimetreDuRole = (string) $role->access_scope;
        $this->onglet = 'roles';
    }

    public function enregistrerLeRole(): void
    {
        $this->message = $this->erreur = null;

        $this->validate([
            'nomDuRole' => ['required', 'string', 'max:80'],
            'perimetreDuRole' => ['nullable', 'in:'.implode(',', $this->perimetresPossibles())],
        ]);

        $service = app(GestionDesRolesService::class);
        $perimetre = $this->perimetreDuRole === '' ? null : $this->perimetreDuRole;

        try {
            if ($this->roleEnEdition === null) {
                $service->creerUnRole(auth()->user(), $this->nomDuRole, $this->capacitesDuRole, $perimetre);
            } else {
                $service->modifierUnRole(
                    auth()->user(),
                    AdminRole::findOrFail($this->roleEnEdition),
                    $this->nomDuRole,
                    $this->capacitesDuRole,
                    $perimetre,
                );
            }
        } catch (DomainException $e) {
            $this->erreur = $e->getMessage();

            return;
        }

        $this->nouveauRole();
        $this->message = __('Rôle enregistré.');
        unset($this->roles, $this->administrateurs, $this->parCapacite);
    }

    public function supprimerLeRole(int $roleId): void
    {
        app(GestionDesRolesService::class)->supprimerUnRole(AdminRole::findOrFail($roleId));

        $this->nouveauRole();
        $this->message = __('Rôle supprimé. Les comptes concernés gardent leurs capacités individuelles.');
        unset($this->roles, $this->administrateurs, $this->parCapacite);
    }

    // ── Les administrateurs ────────────────────────────────────────────────

    public function editerLAdministrateur(int $userId): void
    {
        $this->message = $this->erreur = null;

        $admin = User::findOrFail($userId);

        $this->adminEnEdition = $admin->id;
        $this->roleAssigne = $admin->admin_role_id;
        // LES CAPACITÉS DU RÔLE NE SE COCHENT PAS ICI : seules celles posées EN PLUS s'éditent,
        // sinon les décocher donnerait l'illusion de les retirer.
        $this->capacitesEnPlus = array_values(array_diff($admin->permissionList(), $admin->capacitesDuRole()));
        // TROIS PERIMETRES ONT UN SENS POUR UN ADMINISTRATEUR, et trois seulement :
        // `isReadOnlyAdmin()` lit `readonly`, `isZoneScopedAdmin()` lit `zone`, tout le reste
        // vaut `all`. Les constantes `own`, `organization` et `global` existent et ne sont
        // lues nulle part — les afficher telles quelles rendait le formulaire invalide au
        // chargement, et l'enregistrement echouait sans un mot.
        $this->perimetre = in_array($admin->access_scope, [User::ACCESS_SCOPE_ZONE, User::ACCESS_SCOPE_READONLY], true)
            ? (string) $admin->access_scope
            : User::ACCESS_SCOPE_ALL;
        $this->zoneGeree = $admin->managed_service_zone_id;
        $this->onglet = 'personnes';
    }

    public function annulerLEdition(): void
    {
        $this->reset(['adminEnEdition', 'roleAssigne', 'capacitesEnPlus', 'perimetre', 'zoneGeree']);
    }

    public function enregistrerLAdministrateur(): void
    {
        $this->message = $this->erreur = null;

        $this->validate([
            'perimetre' => ['required', 'in:'.implode(',', $this->perimetresPossibles())],
            'zoneGeree' => ['nullable', 'integer'],
        ]);

        try {
            app(GestionDesRolesService::class)->appliquerA(
                auth()->user(),
                User::findOrFail((int) $this->adminEnEdition),
                $this->roleAssigne === null ? null : AdminRole::findOrFail($this->roleAssigne),
                $this->capacitesEnPlus,
                $this->perimetre,
                $this->zoneGeree,
            );
        } catch (DomainException $e) {
            $this->erreur = $e->getMessage();

            return;
        }

        $this->annulerLEdition();
        $this->message = __('Capacités enregistrées.');
        unset($this->administrateurs, $this->roles, $this->parCapacite);
    }

    /** @return list<string> */
    private function perimetresPossibles(): array
    {
        return [User::ACCESS_SCOPE_ALL, User::ACCESS_SCOPE_ZONE, User::ACCESS_SCOPE_READONLY];
    }

    public function render(): View
    {
        return view('livewire.admin.roles-et-permissions');
    }
}

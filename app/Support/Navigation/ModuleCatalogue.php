<?php

namespace App\Support\Navigation;

use App\Models\OrganizationAccount;
use App\Services\PermissionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Lecture du registre `config/modules.php`.
 *
 * @phpstan-type Module array{key: string, label: string, icon: string, route: string, context: string, category: string, primary: bool, visible_si?: string, permission?: string|list<string>, gate?: string}
 * @phpstan-type GroupeDeModules array{category: string, label: string, modules: non-empty-array<int, Module>}
 */
class ModuleCatalogue
{
    /**
     * Les modules d'un contexte, groupés par catégorie, dans l'ordre du registre.
     *
     * @return Collection<int, GroupeDeModules>
     */
    public static function pourContexte(string $contexte): Collection
    {
        $modules = self::visibles($contexte);

        /** @var array<string, string> $categories */
        $categories = config('modules.categories');

        return collect($categories)
            ->map(fn (string $libelle, string $cle): array => [
                'category' => $cle,
                'label' => $libelle,
                'modules' => $modules->where('category', $cle)->values()->all(),
            ])
            ->filter(fn (array $groupe): bool => $groupe['modules'] !== [])
            ->values();
    }

    /**
     * Les entrées qui restent dans la navbar allégée — cinq au plus par contexte.
     *
     * @return Collection<int, Module>
     */
    public static function principaux(string $contexte): Collection
    {
        return self::visibles($contexte)->where('primary', true)->values();
    }

    /**
     * LA PASSERELLE VERS L'ESPACE SOCIÉTÉ — un changement d'espace, pas un module parmi d'autres.
     *
     * @return Module|null
     */
    public static function porteVersLEspaceSociete(): ?array
    {
        return self::visibles('client')
            ->firstWhere('route', 'client-company.dashboard');
    }

    /**
     * Une case dont la route n'existe pas promet une page et rend un 404.
     *
     * @return Collection<int, Module>
     */
    private static function visibles(string $contexte): Collection
    {
        /** @var list<Module> $catalogue */
        $catalogue = config('modules.catalogue');

        return collect($catalogue)
            // `*` DÉSIGNE LES MODULES TRANSVERSAUX — profil, notifications, aide, textes légaux.
            ->filter(fn (array $module): bool => $module['context'] === $contexte || $module['context'] === '*')
            ->filter(fn (array $module): bool => Route::has($module['route']))
            ->filter(fn (array $module): bool => self::autoriseeParSaCondition($module))
            ->filter(fn (array $module): bool => self::autoriseeParSaPermission($module))
            ->filter(fn (array $module): bool => self::autoriseeParSonGateAdmin($module))
            ->values();
    }

    /**
     * UNE CASE QUI MÈNE À UN 403 EST PIRE QU'UNE CASE ABSENTE.
     *
     * @param  Module  $module
     */
    private static function autoriseeParSaPermission(array $module): bool
    {
        $permission = $module['permission'] ?? null;

        if ($permission === null) {
            return true;
        }

        // UNE LISTE SIGNIFIE « L'UNE OU L'AUTRE », PARCE QUE CERTAINS ÉCRANS ONT DEUX PORTES.
        $exigences = is_array($permission) ? $permission : [$permission];

        $utilisateur = Auth::user();

        if ($utilisateur === null) {
            return false;
        }

        // Pas de garde `method_exists` : `Auth::user()` est typé `User`, qui porte le trait
        // `HasOrganizationContext`. Une garde que le type rend toujours vraie donne l'illusion
        // d'une protection.
        $organisationId = $utilisateur->organizationContextId();

        if ($organisationId === null) {
            return false;
        }

        $organisation = OrganizationAccount::find($organisationId);

        if ($organisation === null) {
            return false;
        }

        $permissions = app(PermissionService::class);

        foreach ($exigences as $exigence) {
            if ($permissions->can($utilisateur, $exigence, $organisation)) {
                return true;
            }
        }

        return false;
    }

    /**
     * CERTAINES CASES NE CONCERNENT QU'UNE PARTIE DES COMPTES D'UN CONTEXTE.
     *
     * @param  Module  $module
     */
    private static function autoriseeParSaCondition(array $module): bool
    {
        $condition = $module['visible_si'] ?? null;

        if ($condition === null) {
            return true;
        }

        $utilisateur = Auth::user();

        if ($utilisateur === null || ! method_exists($utilisateur, $condition)) {
            return false;
        }

        return $utilisateur->{$condition}() === true;
    }

    /**
     * LE GATE D'ADMINISTRATION — la troisieme cle, et elle manquait.
     *
     * @param  Module  $module
     */
    private static function autoriseeParSonGateAdmin(array $module): bool
    {
        $gate = $module['gate'] ?? null;

        if ($gate === null) {
            return true;
        }

        return Gate::allows($gate);
    }
}

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
     * LE REGISTRE, AUGMENTE DES ECRANS PERSONNELS RAPATRIES DANS LES ESPACES SOCIETE.
     *
     * Les cinquante entrees jumelles ne sont pas ecrites dans `config/modules.php` : deux
     * listes auraient diverge des la premiere route ajoutee.
     *
     * @return Collection<int, Module>
     */
    public static function catalogueComplet(): Collection
    {
        /** @var list<Module> $catalogue */
        $catalogue = config('modules.catalogue');

        $complet = collect($catalogue);

        foreach (EspaceCourant::FUSIONS as $societe => $personnel) {
            // Une case de l'espace societe qui pointait deja vers un ecran personnel prend sa
            // jumelle fusionnee : sinon chaque clic passait par une redirection.
            $complet = $complet->map(fn (array $module): array => $module['context'] === $societe
                ? [...$module, 'route' => self::jumelle($module['route'], $personnel, $societe)]
                : $module);

            $complet = $complet->concat(self::ecransRapatries($personnel, $societe));
        }

        return $complet->values();
    }

    /**
     * LES ROUTES ASSUMEES SANS CASE, JUMELLES COMPRISES.
     *
     * Un export CSV n'est pas un module. Sa jumelle fusionnee ne l'est pas davantage, et
     * l'inscrire une seconde fois dans `non_modules` aurait fait deux listes a tenir.
     *
     * @return list<string>
     */
    public static function sansCaseAssumee(): array
    {
        /** @var array<string, string> $raisons */
        $raisons = config('modules.non_modules');

        $noms = array_keys($raisons);

        foreach (EspaceCourant::FUSIONS as $societe => $personnel) {
            foreach (array_keys($raisons) as $nom) {
                $jumelle = self::jumelle($nom, $personnel, $societe);

                if ($jumelle !== $nom) {
                    $noms[] = $jumelle;
                }
            }
        }

        return array_values(array_unique($noms));
    }

    /** Le nom de la route sous l'espace societe, ou le nom d'origine s'il n'y en a pas. */
    private static function jumelle(string $route, string $personnel, string $societe): string
    {
        $prefixe = $personnel.'.';

        if (! str_starts_with($route, $prefixe)) {
            return $route;
        }

        $jumelle = $societe.'.perso.'.substr($route, strlen($prefixe));

        return Route::has($jumelle) ? $jumelle : $route;
    }

    /**
     * Les ecrans d'un espace personnel, vus depuis l'espace societe qui les accueille.
     *
     * @return Collection<int, Module>
     */
    private static function ecransRapatries(string $personnel, string $societe): Collection
    {
        /** @var list<Module> $catalogue */
        $catalogue = config('modules.catalogue');

        $prefixe = $personnel.'.';

        // Ce que l'espace societe porte deja ne se dedouble pas : « Prendre rendez-vous » et
        // « Mes donnees RGPD » y figuraient avant la fusion.
        $dejaLa = collect($catalogue)->where('context', $societe)->pluck('route')->all();

        // « Contrats » et « Litiges » existent des DEUX cotes, et designent deux ecrans
        // differents. Sans marque, le repertoire fusionne affichait deux cases jumelles.
        $libellesDeLaSociete = collect($catalogue)->where('context', $societe)->pluck('label')->all();

        return collect($catalogue)
            ->filter(fn (array $module): bool => $module['context'] === $personnel)
            ->reject(fn (array $module): bool => in_array($module['route'], $dejaLa, true))
            // L'accueil et le repertoire sont ceux de la societe : deux d'un meme espace.
            ->reject(fn (array $module): bool => in_array(
                substr($module['route'], strlen($prefixe)),
                EspaceCourant::REPRIS_PAR_LA_SOCIETE,
                true
            ))
            ->map(function (array $module) use ($personnel, $societe, $libellesDeLaSociete): array {
                // Une route partagee, ou declaree hors du groupe re-declare
                // (`employe.google.calendar` vit dans `routes/integrations.php`), vaut telle quelle.
                $route = self::jumelle($module['route'], $personnel, $societe);

                $libelle = in_array($module['label'], $libellesDeLaSociete, true)
                    ? $module['label'].' (personnel)'
                    : $module['label'];

                return [
                    ...$module,
                    'key' => $societe.':'.$route,
                    'label' => $libelle,
                    'route' => $route,
                    'context' => $societe,
                    // La barre garde les principaux de la societe.
                    'primary' => false,
                ];
            })
            ->values();
    }

    /**
     * Une case dont la route n'existe pas promet une page et rend un 404.
     *
     * @return Collection<int, Module>
     */
    private static function visibles(string $contexte): Collection
    {
        return self::catalogueComplet()
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

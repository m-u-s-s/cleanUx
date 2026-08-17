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
 * Toute la navigation du web passe par ici : la page Modules, la navbar et les deux layouts
 * société. C'est ce qui remplace les quatre registres inline qui vivaient chacun dans sa vue.
 *
 * @phpstan-type Module array{key: string, label: string, icon: string, route: string, context: string, category: string, primary: bool, visible_si?: string, permission?: string|list<string>, gate?: string}
 * Le groupe porte `non-empty-array` et non `array` : `pourContexte()` retire les catégories vides,
 * donc un groupe rendu a forcément au moins une case. `Collection` n'étant pas covariante en
 * PHPStan, le type déclaré doit être exactement celui qui sort.
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
     * Elle reste dans la barre, alors que le reste est descendu dans la page Modules : l'enfouir
     * d'un cran a déjà coûté cher dans ce dépôt, où l'espace société est resté sans porte d'entrée.
     * Elle est lue depuis le registre plutôt que réécrite dans la vue, pour que sa condition de
     * visibilité soit tenue au même endroit que celle de sa case.
     *
     * @return Module|null
     */
    public static function porteVersLEspaceSociete(): ?array
    {
        return self::visibles('client')
            ->firstWhere('route', 'client-company.dashboard');
    }

    /**
     * Une case dont la route n'existe pas promet une page et rend un 404. `Route::has` est le seul
     * juge : les routes varient selon les modules activés, et le registre ne peut pas le savoir.
     *
     * @return Collection<int, Module>
     */
    private static function visibles(string $contexte): Collection
    {
        /** @var list<Module> $catalogue */
        $catalogue = config('modules.catalogue');

        return collect($catalogue)
            /*
             * `*` DÉSIGNE LES MODULES TRANSVERSAUX — profil, notifications, aide, textes légaux.
             *
             * Ces pages ne vivent sous aucun tableau de bord (`user/profile`, `notifications`,
             * `aide`, `legal/*`), si bien qu'elles n'appartenaient à aucun contexte : les cinq
             * espaces étaient sans profil ni notifications dans leur page Modules. Les recopier
             * cinq fois serait cinq occasions d'en oublier un — c'est précisément ce que le
             * registre unique a supprimé.
             */
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
     * Les entrées de l'espace société ne portaient aucune clé de permission : la navbar d'un
     * `worker` affichait Dispatch, Équipe, Équipes terrain et Sites desservis — quatre liens qui
     * répondent 403 depuis que le lot 1 a posé les gardes. Un menu qui promet ce qu'il ne peut pas
     * ouvrir est une régression d'usage, même quand la sécurité, elle, est correcte.
     *
     * La permission est évaluée contre l'organisation ACTIVE du compte, celle-là même que le
     * middleware `org.permission` interroge — deux lectures de la même règle qui divergeraient
     * rendraient le menu menteur dans un sens ou dans l'autre.
     *
     * @param  Module  $module
     */
    private static function autoriseeParSaPermission(array $module): bool
    {
        $permission = $module['permission'] ?? null;

        if ($permission === null) {
            return true;
        }

        /*
         * UNE LISTE SIGNIFIE « L'UNE OU L'AUTRE », PARCE QUE CERTAINS ÉCRANS ONT DEUX PORTES.
         *
         * `QualityAndFleet` s'ouvre par `missions.quality` OU `fleet.view` — le responsable qualité
         * et le gestionnaire de flotte y entrent chacun par la sienne. `GovernanceCenter` de même,
         * par `finance.view` OU `bookings.approve`. Tant que le registre ne savait exprimer qu'une
         * seule clé, sa case était forcément plus stricte que son écran : le directeur opérations
         * pouvait approuver une demande sans qu'aucune case ne l'y mène.
         *
         * On exige donc AU MOINS UNE des clés, jamais toutes : exiger les deux refermerait l'écran
         * sur les deux métiers qu'il est censé servir.
         */
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
     * La porte vers l'espace société n'a de sens que pour un client qui appartient à une société :
     * la montrer à un particulier lui promettrait une page qui répondra 403, et une liste qui ment
     * sur ce qu'on peut ouvrir vaut moins que pas de liste. L'ancienne navbar portait cette
     * condition en dur ; le registre la porte désormais.
     *
     * Le nom de méthode vient du fichier de configuration — du code, pas d'une entrée utilisateur.
     * On vérifie tout de même son existence : une faute de frappe masquerait silencieusement une
     * case, ce qui est exactement le genre de disparition que ce dépôt cherche à rendre visible.
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
     * Ce catalogue filtrait sur trois choses : le contexte, l'existence de la route, et la
     * permission d'ORGANISATION. Aucune ne dit ce qu'un administrateur a le droit de faire :
     * `platform_role` porte quinze capacites distinctes -- `manage-finance`, `manage-users`,
     * `manage-face-check`... -- et un administrateur au perimetre restreint voyait donc les
     * quatre-vingt-six tuiles de l'espace, y compris celles qui lui repondent 403.
     *
     * C'est exactement ce que dit le commentaire de `autoriseeParSaPermission()` quelques lignes
     * plus haut : « une case qui mene a un 403 est pire qu'une case absente ». La regle etait
     * ecrite, elle n'etait appliquee qu'aux permissions d'organisation.
     *
     * `Route::has()` NE SUFFIT PAS, et ce depot le sait : la porte existe ne veut pas dire qu'on a
     * la cle.
     *
     * LE GATE EST DECLARE PAR LE MODULE, pas devine depuis sa categorie. Une correspondance
     * approximative masquerait des ecrans a des administrateurs qui y ont droit -- une regression
     * silencieuse, et bien pire que le defaut qu'on corrige.
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

<?php

namespace App\Support\Navigation;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Lecture du registre `config/modules.php`.
 *
 * Toute la navigation du web passe par ici : la page Modules, la navbar et les deux layouts
 * société. C'est ce qui remplace les quatre registres inline qui vivaient chacun dans sa vue.
 *
 * @phpstan-type Module array{key: string, label: string, icon: string, route: string, context: string, category: string, primary: bool}
 * Le groupe porte `non-empty-array` et non `array` : `pourContexte()` retire les catégories vides,
 * donc un groupe rendu a forcément au moins une case. `Collection` n'étant pas covariante en
 * PHPStan, le type déclaré doit être exactement celui qui sort.
 *
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
            ->where('context', $contexte)
            ->filter(fn (array $module): bool => Route::has($module['route']))
            ->values();
    }
}

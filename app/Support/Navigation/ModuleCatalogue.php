<?php

namespace App\Support\Navigation;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Lecture du registre `config/modules.php`.
 *
 * Toute la navigation du web passe par ici : la page Modules, la navbar et les deux layouts
 * société. C'est ce qui remplace les quatre registres inline qui vivaient chacun dans sa vue.
 */
class ModuleCatalogue
{
    /** Les modules d'un contexte, groupés par catégorie, dans l'ordre du registre. */
    public static function pourContexte(string $contexte): Collection
    {
        $modules = self::visibles($contexte);

        return collect(config('modules.categories'))
            ->map(fn (string $libelle, string $cle) => [
                'category' => $cle,
                'label' => $libelle,
                'modules' => $modules->where('category', $cle)->values()->all(),
            ])
            ->filter(fn (array $groupe) => $groupe['modules'] !== [])
            ->values();
    }

    /** Les entrées qui restent dans la navbar allégée — cinq au plus par contexte. */
    public static function principaux(string $contexte): Collection
    {
        return self::visibles($contexte)->where('primary', true)->values();
    }

    /**
     * Une case dont la route n'existe pas promet une page et rend un 404. `Route::has` est le seul
     * juge : les routes varient selon les modules activés, et le registre ne peut pas le savoir.
     */
    private static function visibles(string $contexte): Collection
    {
        return collect(config('modules.catalogue'))
            ->where('context', $contexte)
            ->filter(fn (array $module) => Route::has($module['route']))
            ->values();
    }
}

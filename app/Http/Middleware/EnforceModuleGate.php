<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/** LA CAPACITÉ DÉCLARÉE PAR UN MODULE FERME AUSSI SA PORTE. */
class EnforceModuleGate
{
    public function handle(Request $request, Closure $next): Response
    {
        $gate = $this->gateDeLaRoute($request->route()?->getName())
            ?? $this->gateDeLUrlDApi($request->path());

        if ($gate !== null) {
            abort_unless(Gate::allows($gate), 403);
        }

        return $next($request);
    }

    /** La capacité déclarée par le module servi par cette route, s'il y en a une. */
    private function gateDeLaRoute(?string $nomDeRoute): ?string
    {
        if ($nomDeRoute === null) {
            return null;
        }

        foreach ((array) config('modules.catalogue', []) as $module) {
            if (($module['route'] ?? null) !== $nomDeRoute) {
                continue;
            }

            $gate = $module['gate'] ?? null;

            if (is_string($gate) && $gate !== '') {
                return $gate;
            }
        }

        return null;
    }

    /** LE REPLI DE LA SURFACE API — et pourquoi il ne contredit pas la règle ci-dessus. */
    private function gateDeLUrlDApi(string $chemin): ?string
    {
        if (! str_starts_with($chemin, 'api/admin/')) {
            return null;
        }

        $segments = explode('/', $chemin);
        $segment = $segments[2] ?? '';

        if ($segment === '') {
            return null;
        }

        // LA CONSOLE GÉNÉRIQUE N'EST PAS UN MODULE, elle les sert TOUS.
        if ($segment === 'console') {
            $cle = ($segments[3] ?? '') === 'reports'
                ? ($segments[4] ?? '')
                : ($segments[3] ?? '');

            return $cle === '' ? null : $this->gateDuModuleDeConsole($cle);
        }

        foreach ((array) config('modules.catalogue', []) as $module) {
            if (($module['context'] ?? null) !== 'admin') {
                continue;
            }

            $route = (string) ($module['route'] ?? '');

            if ($route === '' || ! str_starts_with($route, 'admin.'.$segment)) {
                continue;
            }

            $gate = $module['gate'] ?? null;

            if (is_string($gate) && $gate !== '') {
                return $gate;
            }
        }

        return null;
    }

    /** La capacité du module servi par une ressource de la console générique. */
    private function gateDuModuleDeConsole(string $cleDeRessource): ?string
    {
        $routesWeb = [];

        foreach ((array) config('admin_console.modules', []) as $module) {
            if (($module['key'] ?? null) === $cleDeRessource) {
                $routesWeb = (array) ($module['routes'] ?? []);
                break;
            }
        }

        if ($routesWeb === []) {
            return null;
        }

        $routeur = app('router')->getRoutes();

        foreach ((array) config('modules.catalogue', []) as $module) {
            if (($module['context'] ?? null) !== 'admin') {
                continue;
            }

            $gate = $module['gate'] ?? null;

            if (! is_string($gate) || $gate === '') {
                continue;
            }

            $uri = $routeur->getByName((string) ($module['route'] ?? ''))?->uri();

            if ($uri !== null && in_array($uri, $routesWeb, true)) {
                return $gate;
            }
        }

        return null;
    }
}

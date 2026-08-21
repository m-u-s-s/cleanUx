<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * LA CAPACITÉ DÉCLARÉE PAR UN MODULE FERME AUSSI SA PORTE.
 *
 * `config/modules.php` sert à deux choses qui doivent dire la même : ce que la navigation AFFICHE,
 * et ce à quoi l'on a DROIT. Elles ne le disaient pas.
 *
 * MESURÉ AVANT D'ÉCRIRE : sur quatre-vingt-six routes d'administration, **une seule** portait un
 * contrôle de capacité (`can:manage-face-check`), et trois composants Livewire sur cent trois en
 * vérifiaient une. `EnforcesAdminAccess`, présent partout, ne demande que « est-ce un
 * administrateur ». Autrement dit : les quinze capacités de `platform_role` existaient, et
 * n'interdisaient presque rien.
 *
 * ── POURQUOI UN INTERMÉDIAIRE PLUTÔT QUE `can:` SUR CHAQUE ROUTE ─────────────────────────────
 *
 * Parce que la double écriture est le défaut qu'on répare. Poser la capacité une fois dans le
 * catalogue et une seconde fois sur la route, c'est créer deux copies qui divergeront — et c'est
 * toujours la plus permissive qui décidera. Ce dépôt en collectionne les exemples.
 *
 * Ici, `config/modules.php` fait foi. Déclarer `'gate' => 'manage-finance'` sur un module masque sa
 * tuile ET ferme son écran, du même geste. Un module sans clé `gate` reste ouvert à tout
 * administrateur, exactement comme avant : l'ajout ne ferme rien tant que personne ne l'a demandé.
 *
 * ── CE QUE CET INTERMÉDIAIRE ÉVITE, ET QUI EST PIRE QUE LE DÉFAUT D'ORIGINE ──────────────────
 *
 * Gater la seule navigation aurait produit une porte INVISIBLE MAIS OUVERTE. Le commentaire de
 * `ModuleCatalogue` prévient déjà dans l'autre sens — « une case qui mène à un 403 est pire qu'une
 * case absente » — et l'inverse est un trou de sécurité, pas une gêne d'usage.
 */
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

    /**
     * La capacité déclarée par le module servi par cette route, s'il y en a une.
     *
     * ON COMPARE SUR LE NOM DE ROUTE, pas sur l'URL : c'est la clé que le catalogue emploie déjà
     * pour décider de l'affichage, et se fier au chemin ferait diverger les deux lectures dès le
     * premier préfixe modifié.
     */
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

    /**
     * LE REPLI DE LA SURFACE API — et pourquoi il ne contredit pas la règle ci-dessus.
     *
     * Sur le web, on compare le NOM de route, parce que le catalogue en emploie un. Côté API,
     * ce nom n'existe pas : sur les cent dix-neuf routes `api/admin/*`, **une seule** est
     * nommée. Comparer sur le nom y revient donc à ne rien garder du tout — et c'est
     * exactement ce qui se passait : un administrateur limité à `manage-quality` recevait 403
     * sur `/admin/accounting-v2` et 200 sur `/api/admin/accounting-v2/entries`, la même
     * comptabilité par l'autre porte.
     *
     * On rattache donc la route à son module par le SEGMENT qui suit `api/admin/`, en cherchant
     * le module d'administration dont la route commence par `admin.<segment>`. La source de
     * vérité reste `config/modules.php` : rien n'est déclaré deux fois, c'est la CLÉ de lecture
     * qui s'adapte à une surface qui n'a pas de noms.
     *
     * Un segment sans module correspondant ne ferme rien — comme un module sans clé `gate`.
     */
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

        /*
         * LA CONSOLE GÉNÉRIQUE N'EST PAS UN MODULE, elle les sert TOUS.
         *
         * `api/admin/console/{ressource}` mène aux quatre-vingt-onze ressources du moteur —
         * comptabilité, finances, utilisateurs, litiges. S'arrêter au segment « console »
         * ne trouvait aucun module correspondant, donc aucune capacité à vérifier : un
         * administrateur limité à `manage-quality` lisait la comptabilité depuis le mobile,
         * alors que le web la lui refusait.
         *
         * La ressource porte la même clé que son module dans `config/admin_console.php` —
         * lequel connaît les routes WEB du module. On remonte donc jusqu'à la capacité que
         * `config/modules.php` déclare pour ces routes : la règle reste écrite une seule
         * fois, c'est seulement le chemin pour l'atteindre qui est plus long.
         */
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

    /**
     * La capacité du module servi par une ressource de la console générique.
     *
     * `config/admin_console.php` fait le lien entre la clé de ressource et les routes WEB
     * du module ; `config/modules.php` porte la capacité de ces routes. On traverse les
     * deux plutôt que de recopier la capacité dans le premier : deux déclarations
     * divergeraient, et c'est toujours la plus permissive qui déciderait.
     */
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

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
        $gate = $this->gateDeLaRoute($request->route()?->getName());

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
}

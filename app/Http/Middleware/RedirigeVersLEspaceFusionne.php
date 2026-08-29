<?php

namespace App\Http\Middleware;

use App\Support\Navigation\EspaceCourant;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * UN SEUL ESPACE, PAS DEUX.
 *
 * Un compte rattache a une societe avait son espace personnel ET celui de sa societe. Les
 * ecrans personnels vivent desormais sous le prefixe de la societe : cette porte renvoie
 * les anciennes adresses vers leur jumelle fusionnee. Un compte SANS societe n'est jamais
 * redirige — l'espace personnel est alors le seul qu'il ait.
 */
class RedirigeVersLEspaceFusionne
{
    /** L'accueil et le repertoire de l'espace fusionne sont ceux de la societe. */
    private const REPRIS_PAR_LA_SOCIETE = ['dashboard', 'modules'];

    /** @var array<string, array{0: string, 1: string}> prefixe personnel => [type d'organisation, prefixe societe] */
    private const ESPACES = [
        'client.' => ['client', 'client-company.'],
        'employe.' => ['provider', 'provider-company.'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $nom = (string) ($request->route()?->getName() ?? '');

        foreach (self::ESPACES as $prefixePersonnel => [$typeAttendu, $prefixeSociete]) {
            if (! str_starts_with($nom, $prefixePersonnel)) {
                continue;
            }

            if (! EspaceCourant::organisationEstDeType($request->user(), $typeAttendu)) {
                return $next($request);
            }

            $cible = $this->jumelle($nom, $prefixePersonnel, $prefixeSociete);

            // Une route absente laisse passer : mieux vaut l'ancienne page que rien.
            if ($cible === null) {
                return $next($request);
            }

            // UNE REPONSE CONSTRUITE, PAS DEMANDEE AU CONTENEUR. Livewire y remplace le
            // redirecteur pendant qu'un composant se rend et ne le restitue pas si le rendu
            // est interrompu : `redirect()` renvoie alors un objet que le routeur ne rend pas.
            return new RedirectResponse(route($cible, $request->route()->parameters()));
        }

        return $next($request);
    }

    private function jumelle(string $nom, string $prefixePersonnel, string $prefixeSociete): ?string
    {
        $suffixe = substr($nom, strlen($prefixePersonnel));

        $cible = in_array($suffixe, self::REPRIS_PAR_LA_SOCIETE, true)
            ? $prefixeSociete.$suffixe
            : $prefixeSociete.'perso.'.$suffixe;

        return Route::has($cible) ? $cible : null;
    }
}

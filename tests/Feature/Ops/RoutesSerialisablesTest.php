<?php

namespace Tests\Feature\Ops;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Routeur;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `php artisan route:cache` DOIT RÉUSSIR, SINON AUCUN DÉPLOIEMENT NE VA AU BOUT.
 *
 * POURQUOI CE FICHIER EXISTE. Le cache de routes échouait sur ce dépôt, avec ce message :
 *
 *   Unable to prepare route [admin/onboarding-documents/{document}/file] for serialization.
 *   Another route has already been assigned name [admin.onboarding.document.file].
 *
 * Deux routes portaient le même nom — la route web signée de routes/admin.php et une route d'API
 * de routes/api/provider.php. Ce doublon cassait DEUX choses d'un coup :
 *
 *   1. le cache de routes, donc l'étape `route:cache` de tout déploiement ;
 *   2. l'aperçu de document dans l'espace admin, car `route()` ne rend qu'une URL par nom et c'est
 *      la DERNIÈRE enregistrée qui gagne. `URL::temporarySignedRoute` fabriquait donc une URL
 *      /api/ dépourvue de session : un 401 pour tout administrateur.
 *
 * CE QUE CE TEST NE MESURE PAS, ET POURQUOI. Il n'appelle jamais `route:cache`. Cette commande
 * écrit dans bootstrap/cache/, partagé par tout le processus : un cache laissé en place ferait
 * dérailler les tests suivants, et un test qui casse ses voisins finit par être désactivé. Il
 * reproduit donc le MÉCANISME de la commande — l'unicité des noms, puis la sérialisation route par
 * route — sans en produire les effets de bord.
 *
 * L'AUDIT SE TROMPAIT SUR LA CAUSE, et il vaut la peine de l'écrire ici : il attribuait l'échec aux
 * routes à action Closure et demandait d'en convertir trente-quatre en contrôleurs. C'est faux
 * depuis Laravel 8 — `Route::prepareForSerialization()` sérialise les closures via
 * `SerializableClosure`. Vérifié : une fois le doublon levé, `route:cache` réussit avec les
 * trente-six closures toujours en place. Le troisième test ci-dessous fige ce fait, pour que
 * personne ne relance ce chantier sans raison.
 */
class RoutesSerialisablesTest extends TestCase
{
    #[Test]
    public function aucun_nom_de_route_n_est_porte_par_deux_routes(): void
    {
        /** @var array<string, list<string>> $uriParNom */
        $uriParNom = [];

        foreach (Routeur::getRoutes()->getRoutes() as $route) {
            $nom = $route->getName();

            if ($nom === null) {
                continue;
            }

            $uriParNom[$nom][] = implode('|', $route->methods()).' '.$route->uri();
        }

        $doublons = array_filter($uriParNom, static fn (array $uris): bool => count($uris) > 1);

        // Le message NOMME le coupable et ses URI : sans cela, le prochain à casser ça devrait
        // relancer route:cache à l'aveugle pour découvrir laquelle des 850 routes est en cause.
        $this->assertSame([], $doublons, sprintf(
            "%d nom(s) de route porté(s) par plusieurs routes — `route:cache` refusera de sérialiser :\n%s",
            count($doublons),
            implode("\n", array_map(
                static fn (string $nom, array $uris): string => "  {$nom}\n    - ".implode("\n    - ", $uris),
                array_keys($doublons),
                $doublons,
            )),
        ));
    }

    /**
     * LE NOM DOIT DÉSIGNER LA ROUTE WEB SIGNÉE, PAS CELLE DE L'API.
     *
     * C'est la moitié « produit » du même défaut. Le test précédent tomberait aussi si l'on
     * réintroduisait le doublon, mais il ne dirait rien du SENS du renommage : renommer la route
     * web au lieu de celle d'API lèverait le doublon tout en laissant l'aperçu cassé.
     */
    #[Test]
    public function le_nom_du_fichier_d_onboarding_designe_la_route_web_avec_session(): void
    {
        $url = route('admin.onboarding.document.file', ['document' => 1]);

        $chemin = parse_url($url, PHP_URL_PATH) ?: '';

        $this->assertStringStartsWith('/admin/', $chemin, sprintf(
            "Le nom doit résoudre vers la route web signée, pas vers l'API : %s.\n".
            "Une URL /api/ n'a pas de session, donc l'aperçu de document rend 401 pour tout admin.",
            $url,
        ));
        $this->assertStringNotContainsString('/api/', $chemin);
    }

    /**
     * CHAQUE ROUTE DOIT SURVIVRE À LA SÉRIALISATION.
     *
     * On travaille sur un CLONE : `prepareForSerialization()` mute la route — elle détache le
     * routeur et le conteneur — et une route mutilée casserait toutes les requêtes des tests
     * suivants du même processus.
     */
    #[Test]
    public function chaque_route_survit_a_la_serialisation(): void
    {
        $fautives = [];

        foreach (Routeur::getRoutes()->getRoutes() as $route) {
            /** @var Route $copie */
            $copie = clone $route;

            try {
                $copie->prepareForSerialization();
                serialize($copie);
            } catch (\Throwable $e) {
                $fautives[] = sprintf(
                    '%s %s (%s) — %s',
                    implode('|', $route->methods()),
                    $route->uri(),
                    $route->getName() ?? 'sans nom',
                    $e->getMessage(),
                );
            }
        }

        $this->assertSame([], $fautives, sprintf(
            "%d route(s) non sérialisable(s) :\n  %s",
            count($fautives),
            implode("\n  ", $fautives),
        ));
    }
}

<?php

namespace Tests\Feature\Ops;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Routeur;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** `php artisan route:cache` DOIT RÉUSSIR, SINON AUCUN DÉPLOIEMENT NE VA AU BOUT. */
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

    /** LE NOM DOIT DÉSIGNER LA ROUTE WEB SIGNÉE, PAS CELLE DE L'API. */
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

    /** CHAQUE ROUTE DOIT SURVIVRE À LA SÉRIALISATION. */
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

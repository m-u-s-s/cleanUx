<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset Faker's unique() store between tests. The Faker generator is shared across the
        // whole run, so factories with small unique pools (e.g. bothify('SVC-###') = 1000 values)
        // can exhaust late in a large suite and throw OverflowException during create(). Resetting
        // per test keeps the pool bounded and the suite order-independent.
        fake()->unique(true);

        /*
         * AUCUN TEST NE DÉPEND D'UN `npm run build`.
         *
         * Toute page rendue par la suite passe par `@vite`, qui lève
         * `ViteManifestNotFoundException` si `public/build/manifest.json` n'existe pas. Le test
         * échoue alors en 500, et son message parle d'un manifeste — jamais du code qu'il était
         * censé vérifier.
         *
         * CE DÉPÔT L'A PAYÉ DEUX FOIS. Un worktree neuf a produit 91 échecs qui n'avaient que
         * cette cause. Et le job « Money/GDPR (MySQL, FK activées) » — qui n'installe pas Node,
         * puisqu'il vérifie des clés étrangères et non des écrans — a viré au rouge le jour où
         * trois tests d'écran d'administration sont entrés dans son périmètre.
         *
         * `withoutVite()` remplace la directive par une chaîne vide. C'est sans perte : aucun test
         * de ce dépôt n'assertait quoi que ce soit sur les assets construits — vérifié avant
         * d'écrire cette ligne. Ce qu'ils vérifient, c'est qu'une route répond, qu'une garde tient
         * et qu'un composant s'affiche ; le nom haché d'un fichier JavaScript n'en fait pas partie.
         *
         * L'alternative — construire les assets dans chaque job — ferait payer une à deux minutes
         * par exécution pour restaurer une dépendance dont on ne veut pas.
         */
        $this->withoutVite();

        // Tests must never hit the network. The legacy GeocodingService calls
        // Nominatim (OpenStreetMap) via the Http facade when bookings/missions
        // are created; stub it with a deterministic Brussels result so the suite
        // is offline-safe and not flaky.
        //
        // ATTENTION : ce stub ne peut PAS être supplanté par un Http::fake() posé plus tard dans
        // un test. Laravel fusionne les stubs et retient le PREMIER motif qui correspond — celui
        // enregistré ici gagne donc toujours sur les URL Nominatim, y compris face à un '*'. Un
        // test qui croit imposer une autre réponse (échec réseau, adresse introuvable) reçoit en
        // silence le Bruxelles ci-dessous et passe pour une mauvaise raison. Pour piloter le
        // géocodage, injectez un GeocodingService de test plutôt que de stubber le transport HTTP
        // (voir Tests\Feature\Missions\GeocodeMissionDestinationTest).
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '50.8503', 'lon' => '4.3517', 'address' => []],
            ], 200),
        ]);
    }
}

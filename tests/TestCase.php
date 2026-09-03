<?php

namespace Tests;

use App\Models\User;
use App\Services\Platform\SiegeDuSuperAdmin;
use App\Support\Platform\PorteDuSiege;
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

        // AUCUN TEST NE DÉPEND D'UN `npm run build`.
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

    /**
     * UN SUPER-ADMINISTRATEUR, PAR LA PORTE.
     *
     * Le modele refuse desormais qu'on pose ou retire ce role hors de
     * {@see SiegeDuSuperAdmin} : c'est cette garde qui empeche un vol en
     * deux temps. Les tests l'ouvrent explicitement, plutot que de la contourner en ecrivant la
     * colonne — un helper qui contournerait la garde ne prouverait plus rien de ce qu'elle protege.
     *
     * @param  array<string, mixed>  $attributs
     */
    protected function prendreLeSiege(array $attributs = []): User
    {
        return PorteDuSiege::ouvrir(
            fn () => User::factory()->create($attributs + [
                'platform_role' => User::PLATFORM_SUPER_ADMIN,
                'is_active' => true,
            ]),
        );
    }
}

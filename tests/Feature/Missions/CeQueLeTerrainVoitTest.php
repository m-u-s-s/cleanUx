<?php

namespace Tests\Feature\Missions;

use App\Http\Controllers\Api\Provider\ProviderMissionLifecycleController;
use App\Services\Missions\HourlyMissionClock;
use App\Support\Domain\MissionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

/**
 * UNE MISSION VENDUE AU TEMPS N'ÉTAIT JAMAIS RECONNUE CÔTÉ TERRAIN.
 *
 * `MissionEngine::pourReservation()` tranche sur `purchased_minutes` — mais cette colonne était
 * ABSENTE de `BOOKING_COLUMNS`, la liste d'`eager load` du contrôleur. Elle valait donc `null` en
 * silence : le moteur répondait « domicile » pour une mission à l'heure, et l'horloge se taisait.
 *
 * Mesuré le 2026-09-07 sur la mission #22 (120 minutes achetées) : `engine` sortait « domicile »
 * de l'API alors que le même appel en direct rendait « horaire ».
 *
 * C'est le piège déjà payé sur `is_ride` — une colonne oubliée dans cette liste ne lève rien.
 */
class CeQueLeTerrainVoitTest extends TestCase
{
    use RefreshDatabase;

    private function colonnes(): array
    {
        $constante = (new ReflectionClass(ProviderMissionLifecycleController::class))
            ->getConstant('BOOKING_COLUMNS');

        return explode(',', (string) $constante);
    }

    public function test_les_colonnes_qui_decident_du_moteur_sont_chargees(): void
    {
        $colonnes = $this->colonnes();

        // Les trois discriminants de `MissionEngine::pourReservation()`, dans l'ordre où il les lit.
        foreach (['dropoff_lat', 'dropoff_lng', 'purchased_minutes'] as $colonne) {
            $this->assertContains($colonne, $colonnes,
                "Sans `{$colonne}`, le moteur se trompe de parcours SANS RIEN LEVER.");
        }
    }

    public function test_la_destination_d_une_course_est_chargee(): void
    {
        // Le conducteur voyait sa destination dans l'offre puis la perdait dans sa mission.
        foreach (['dropoff_address', 'route_distance_m'] as $colonne) {
            $this->assertContains($colonne, $this->colonnes());
        }
    }

    public function test_le_prix_annonce_est_charge(): void
    {
        // `client_price` n'est ecrit qu'a la cloture : sans `estimated_price`, la case « Prix »
        // reste vide sur toute mission qui n'a pas encore eu lieu.
        $this->assertContains('estimated_price', $this->colonnes());
    }

    public function test_la_regle_horaire_se_connait_avant_le_demarrage(): void
    {
        $horloge = app(HourlyMissionClock::class);

        $this->assertTrue(
            method_exists($horloge, 'regle'),
            'Sans elle, le tarif et la tolerance n’existent qu’une fois la mission commencee.',
        );
    }

    public function test_temoin_le_moteur_lit_bien_ces_colonnes(): void
    {
        // Sans ce contrôle, la liste pourrait porter des colonnes que plus personne ne lit.
        $source = (string) file_get_contents(
            app_path('Support/Domain/MissionEngine.php'),
        );

        $this->assertStringContainsString('purchased_minutes', $source);
        $this->assertStringContainsString('dropoff_lat', $source);
    }
}

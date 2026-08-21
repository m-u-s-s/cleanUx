<?php

namespace Tests\Feature\Admin\Console;

use App\Admin\Console\ResourceRegistry;
use App\Models\Booking;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * L'EXPLORATION MÉTIER, ATTEIGNABLE DEPUIS LA CONSOLE NATIVE.
 *
 * L'écran web croisait les réservations par zone et par service ; le mobile n'avait rien.
 * Le module était déclaré « à venir ». `AnalyticsExplorationResource` sert désormais la
 * liste filtrable au moteur de console générique.
 *
 * Déclarer une couverture ne suffit pas : il faut que la ressource RENDE quelque chose,
 * et que ses filtres filtrent vraiment. Un descripteur qui répond une liste vide serait
 * une case de plus qui ne mène nulle part.
 */
class ExplorationMetierRessourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->adminComplet()->create([
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /** TÉMOIN POSITIF — la liste rend les réservations. */
    public function test_la_liste_rend_les_reservations(): void
    {
        $reservation = Booking::factory()->create(['booking_reference' => 'EXPLO-001']);

        Sanctum::actingAs($this->admin(), ['*']);

        $this->getJson('/api/admin/console/analytics-exploration')
            ->assertOk()
            ->assertSee('EXPLO-001');

        $this->assertNotNull($reservation->fresh());
    }

    /** Le filtre de zone filtre pour de bon — les deux sens sont vérifiés. */
    public function test_le_filtre_de_zone_ecarte_les_autres_zones(): void
    {
        $nord = ServiceZone::factory()->create(['name' => 'Nord']);
        $sud = ServiceZone::factory()->create(['name' => 'Sud']);

        Booking::factory()->create(['booking_reference' => 'DANS-LA-ZONE', 'service_zone_id' => $nord->id]);
        Booking::factory()->create(['booking_reference' => 'HORS-ZONE', 'service_zone_id' => $sud->id]);

        Sanctum::actingAs($this->admin(), ['*']);

        $this->getJson('/api/admin/console/analytics-exploration?filters[service_zone_id]='.$nord->id)
            ->assertOk()
            ->assertSee('DANS-LA-ZONE')
            ->assertDontSee('HORS-ZONE');
    }

    /** REFUS — sans la capacité d'analytique, la console n'ouvre pas la ressource. */
    public function test_un_administrateur_sans_la_capacite_est_refuse(): void
    {
        $restreint = User::factory()->admin()->create([
            'permissions' => ['manage-quality'],
            'two_factor_confirmed_at' => now(),
        ]);

        Sanctum::actingAs($restreint, ['*']);

        $this->getJson('/api/admin/console/analytics-exploration')->assertForbidden();
    }

    /** Le module est déclaré servi, et il l'est réellement. */
    public function test_le_module_est_declare_servi(): void
    {
        $module = collect(config('admin_console.modules'))
            ->firstWhere('key', 'analytics-exploration');

        $this->assertNotNull($module, 'Le module doit exister au registre de la console');
        $this->assertSame('descriptor', $module['coverage']);
        $this->assertNotNull(
            app(ResourceRegistry::class)->for('analytics-exploration'),
            'La couverture « descriptor » exige un descripteur enregistré'
        );
    }
}

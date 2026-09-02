<?php

namespace Tests\Feature\Dispatch;

use App\Enums\ProviderType;
use App\Livewire\Admin\DispatchCenter;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\ProviderProfile;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\Feature\Dispatch\Concerns\OuvreLeCatalogue;
use Tests\TestCase;

/**
 * « IA DISPATCH » ET « MATCHING INSIGHTS » SONT ABSORBEES PAR LE CENTRE DE REPARTITION.
 *
 * Les trois pages repondaient a la meme question — qui prend cette reservation — avec trois
 * moteurs differents, dont DEUX qu'aucun code de production n'appelait. Les trois se declaraient
 * actives a l'ecran. Ce qui a ete porté ici est ce qui decrit la production, et rien d'autre.
 */
class LaRepartitionAbsorbeLesDeuxPagesTest extends TestCase
{
    use OuvreLeCatalogue;
    use RefreshDatabase;

    private const LAT = 50.8467;

    private const LNG = 4.3525;

    /** Un test de disparition exige son temoin : la page qui les absorbe doit, elle, exister. */
    public function test_les_deux_pages_fusionnees_n_ont_plus_de_route(): void
    {
        $this->assertFalse(Route::has('admin.ai.dispatch'), 'La page IA Dispatch est encore routee.');
        $this->assertFalse(Route::has('admin.matching.insights'), 'La page Matching insights est encore routee.');

        // TEMOIN — sans lui, ce test passerait au vert sur une table de routes vide. Le centre
        // etait imbrique DANS la garde d'IA Dispatch : le supprimer l'aurait emporte avec lui.
        $this->assertTrue(Route::has('admin.dispatch.center'), 'Le centre de repartition a disparu avec elles.');
    }

    public function test_le_centre_rend_les_trois_onglets_repris(): void
    {
        Livewire::actingAs($this->administrateur())
            ->test(DispatchCenter::class)
            ->assertSee('Sans intervenant')
            ->assertSee('Poids du score')
            ->assertSee('Métriques prestataires')
            ->set('onglet', 'poids')
            ->assertSee('rating')
            ->set('onglet', 'metriques')
            ->assertSee('Aucune métrique calculée.')
            ->set('onglet', 'sans_intervenant')
            ->assertOk();
    }

    /**
     * L'ACTE PORTE, MAIS REBRANCHE. L'ancien `assign()` ecrivait `employe_id` et confirmait la
     * reservation en direct : aucune offre, aucune ligne d'assignation, aucune garde.
     */
    public function test_imposer_passe_par_le_moteur_et_pose_une_assignation(): void
    {
        $mission = $this->missionPlanifieeSansIntervenant();

        Livewire::actingAs($this->administrateur())
            ->test(DispatchCenter::class)
            ->call('imposer', $mission->id);

        $this->assertDatabaseHas('mission_assignments', [
            'mission_id' => $mission->id,
            'assignment_status' => 'accepted',
        ]);
        $this->assertDatabaseHas('missions', ['id' => $mission->id, 'status' => 'assigned']);
    }

    /** Le refus de reprendre une mission pourvue vient du moteur, pas du composant. */
    public function test_imposer_refuse_une_mission_deja_pourvue(): void
    {
        $mission = $this->missionPlanifieeSansIntervenant();
        $composant = Livewire::actingAs($this->administrateur())->test(DispatchCenter::class);

        $composant->call('imposer', $mission->id);
        $titulaire = (int) $mission->fresh()->lead_provider_user_id;
        $this->assertNotSame(0, $titulaire, 'Temoin : la premiere imposition doit avoir designe quelqu\'un.');

        $composant->call('imposer', $mission->id);

        $this->assertSame($titulaire, (int) $mission->fresh()->lead_provider_user_id);
    }

    private function administrateur(): User
    {
        return User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'managed_service_zone_id' => null,
            'is_active' => true,
        ]);
    }

    private function missionPlanifieeSansIntervenant(): Mission
    {
        $zone = ServiceZone::create([
            'name' => 'Zone fusion', 'slug' => 'zone-fusion', 'code' => 'FUS',
            'status' => 'active', 'is_bookable' => true, 'is_visible' => true,
            'priority' => 10, 'coverage_type' => 'city_cluster',
        ]);

        $trade = Trade::create([
            'slug' => 'plomberie-fusion', 'code' => 'PLB-F', 'name' => 'Plomberie',
            'is_active' => true, 'sort_order' => 1, 'allows_asap' => true,
        ]);

        $this->ouvrirAuCatalogue($trade, $zone);

        $prestataire = User::factory()->create([
            'role' => User::ROLE_EMPLOYE,
            'is_active' => true,
            'primary_service_zone_id' => $zone->id,
        ]);

        ProviderProfile::create([
            'user_id' => $prestataire->id,
            'provider_type' => ProviderType::INDEPENDENT->value,
            'status' => 'active',
            'verification_status' => 'verified',
            'current_lat' => self::LAT,
            'current_lng' => self::LNG,
        ]);

        $prestataire->trades()->syncWithoutDetaching([$trade->id]);

        $booking = Booking::factory()->create([
            'employe_id' => null,
            'assigned_employee_id' => null,
            'service_zone_id' => $zone->id,
            'trade_id' => $trade->id,
            'destination_lat' => self::LAT,
            'destination_lng' => self::LNG,
            'status' => 'confirme',
        ]);

        $mission = Mission::query()->where('booking_id', $booking->id)->firstOrFail();

        $mission->forceFill([
            'status' => MissionStatus::PLANNED,
            'lead_employee_id' => null,
            'lead_provider_user_id' => null,
        ])->save();

        return $mission->fresh();
    }
}

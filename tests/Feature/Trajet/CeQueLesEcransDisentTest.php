<?php

namespace Tests\Feature\Trajet;

use App\Livewire\Employe\MissionActions;
use App\Livewire\Employe\MissionFieldPage;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Dispatch\OfferPayloadBuilder;
use App\Services\GeolocationV2\GeocodingService;
use App\Services\Missions\MissionAssignmentStatusService;
use App\Services\Payments\CommissionService;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * CE QUE LES ÉCRANS DISENT D'UNE COURSE — relevé en la conduisant à la main.
 *
 * Aucun de ces défauts n'empêchait la course d'aboutir, et c'est pour cela qu'aucun test ne les
 * voyait : ils portaient tous sur ce qu'on MONTRE à quelqu'un qui doit décider. Un client qui
 * confirme sans savoir où il va, un chauffeur qui accepte sans savoir la longueur du trajet, une
 * ligne d'assignation qui affirme une acceptation postérieure à l'arrivée.
 */
class CeQueLesEcransDisentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Mission, 2: Booking}
     */
    private function course(bool $course = true): array
    {
        $client = User::factory()->client()->create();
        $prestataire = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $prestataire->id, 'status' => 'active']);

        $booking = Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'employe_id' => $prestataire->id,
            'status' => BookingStatus::CONFIRME,
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'asap',
            'devis_estime' => 4.81,
            'address' => 'Rue de la Loi 1, 1000 Bruxelles',
            'destination_lat' => 50.8467,
            'destination_lng' => 4.3525,
        ] + ($course ? [
            'dropoff_address' => '1050 Ixelles, Belgique',
            'dropoff_lat' => 50.8333,
            'dropoff_lng' => 4.3667,
            'route_distance_m' => 12_400,
            'route_duration_s' => 900,
        ] : []));

        $mission = Mission::create([
            'booking_id' => $booking->id,
            'lead_provider_user_id' => $prestataire->id,
            'status' => MissionStatus::ASSIGNED,
            'destination_lat' => 50.8467,
            'destination_lng' => 4.3525,
        ]);

        MissionAssignment::factory()->accepted()->create([
            'mission_id' => $mission->id,
            'user_id' => $prestataire->id,
        ]);

        return [$prestataire, $mission, $booking];
    }

    /** L'offre disait la rémunération sans dire la longueur de la course. */
    public function test_l_offre_annonce_la_longueur_de_la_course(): void
    {
        [, $mission] = $this->course();
        $assignation = MissionAssignment::where('mission_id', $mission->id)->firstOrFail();

        $charge = app(OfferPayloadBuilder::class)->build($assignation);

        $this->assertTrue($charge['is_ride']);
        $this->assertSame(12.4, $charge['ride_distance_km']);
        $this->assertSame(15, $charge['ride_duration_minutes']);
    }

    /** LE TÉMOIN : une intervention ordinaire n'annonce aucune longueur de course. */
    public function test_une_intervention_ordinaire_n_annonce_pas_de_course(): void
    {
        [, $mission] = $this->course(course: false);
        $assignation = MissionAssignment::where('mission_id', $mission->id)->firstOrFail();

        $charge = app(OfferPayloadBuilder::class)->build($assignation);

        $this->assertFalse($charge['is_ride']);
        $this->assertNull($charge['ride_distance_km']);
    }

    /** La fiche terrain ne disait pas au chauffeur où il devait emmener le client. */
    public function test_la_fiche_terrain_nomme_la_destination(): void
    {
        [$prestataire, $mission] = $this->course();
        $this->actingAs($prestataire);

        Livewire::test(MissionFieldPage::class, ['mission' => $mission])
            ->assertSee('Destination')
            ->assertSee('1050 Ixelles, Belgique')
            ->assertSee('Prise en charge');
    }

    /** LE TÉMOIN : sans point d'arrivée, l'écran reste celui d'avant. */
    public function test_une_intervention_ordinaire_garde_son_unique_adresse(): void
    {
        [$prestataire, $mission] = $this->course(course: false);
        $this->actingAs($prestataire);

        Livewire::test(MissionFieldPage::class, ['mission' => $mission])
            ->assertDontSee('Destination')
            ->assertSee('Adresse');
    }

    /**
     * `accepted_at` s'écrit une fois. La ligne affirmait une acceptation postérieure à l'arrivée.
     */
    public function test_la_date_d_acceptation_ne_se_reecrit_pas(): void
    {
        [$prestataire, $mission] = $this->course();
        $assignation = MissionAssignment::where('mission_id', $mission->id)->firstOrFail();

        // À la seconde : la colonne ne stocke pas les microsecondes, et comparer des Carbon
        // complets ferait échouer ce test pour une raison qui n'a rien à voir avec ce qu'il teste.
        $acceptationInitiale = now()->subMinutes(20)->startOfSecond();
        $assignation->forceFill(['accepted_at' => $acceptationInitiale])->save();

        app(MissionAssignmentStatusService::class)->updateAssignmentStatus(
            $mission,
            $prestataire,
            'arrived',
            ['accepted_at' => now()],
        );

        $this->assertTrue(
            $acceptationInitiale->equalTo($assignation->fresh()->accepted_at),
            'Une ligne affirmait que le chauffeur avait accepté APRÈS être arrivé.'
        );
    }

    /** LE TÉMOIN : une assignation jamais acceptée reçoit bien sa date. */
    public function test_une_assignation_sans_date_recoit_la_sienne(): void
    {
        [$prestataire, $mission] = $this->course();
        $assignation = MissionAssignment::where('mission_id', $mission->id)->firstOrFail();
        $assignation->forceFill(['accepted_at' => null])->save();

        app(MissionAssignmentStatusService::class)->updateAssignmentStatus(
            $mission,
            $prestataire,
            'accepted',
            ['accepted_at' => now()],
        );

        $this->assertNotNull($assignation->fresh()->accepted_at);
    }

    /**
     * LE TAUX ANNONCÉ EST CELUI QUI A ÉTÉ RETENU.
     *
     * Sur une course de 4,81 €, le plancher de 2 € prélève 41 %. Le versement annonçait « 20 % » à
     * côté du montant : le prestataire faisait la division et trouvait autre chose.
     */
    public function test_le_versement_annonce_le_taux_reellement_retenu(): void
    {
        $partage = app(CommissionService::class)->calculateForAmount(481);

        $this->assertSame(200, $partage['platform_fee_cents']);
        $this->assertTrue($partage['minimum_applied']);
        $this->assertEqualsWithDelta(0.4158, $partage['effective_commission_rate'], 0.001);
    }

    /** LE TÉMOIN : sur un montant ordinaire, le plancher ne mord pas et les deux taux coïncident. */
    public function test_sur_un_montant_ordinaire_les_deux_taux_coincident(): void
    {
        $partage = app(CommissionService::class)->calculateForAmount(12_000);

        $this->assertFalse($partage['minimum_applied']);
        $this->assertEqualsWithDelta(
            $partage['commission_rate'],
            $partage['effective_commission_rate'],
            0.0001
        );
    }

    /**
     * La page terrain suit le statut sans qu'on ait à la recharger.
     *
     * Elle affirmait « le tracking devient disponible quand la mission passe en route » alors que
     * la mission venait précisément d'y passer.
     */
    public function test_le_changement_de_statut_est_annonce_a_la_page(): void
    {
        [$prestataire, $mission] = $this->course();
        $this->actingAs($prestataire);

        Livewire::test(MissionActions::class, ['mission' => $mission])
            ->call('setEnRoute')
            ->assertDispatched('mission-statut-change');
    }

    /**
     * En développement, taper une VRAIE adresse ne rendait aucune suggestion : plus l'adresse
     * était réaliste, moins elle fonctionnait.
     */
    public function test_le_geocodeur_de_developpement_accepte_une_adresse_complete(): void
    {
        config()->set('geolocation_v2.provider', 'mock');

        $suggestions = app(GeocodingService::class)->autocomplete('Rue Neuve 12, 1000 Bruxelles', 'BE', 5);

        $this->assertNotEmpty($suggestions);
        $this->assertNotNull(app(GeocodingService::class)->geocode('Rue Neuve 12, 1000 Bruxelles', 'BE'));
    }
}

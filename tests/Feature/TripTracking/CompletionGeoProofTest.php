<?php

namespace Tests\Feature\TripTracking;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\User;
use App\Services\Geo\OnSiteVerifier;
use App\Services\Missions\MissionLifecycleService;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * La clôture se prouve par le code ET par la position.
 *
 * Le pendant de {@see PresenceGeoProofTest}, à l'autre bout de la visite — mais l'enjeu est plus
 * lourd : clôturer encaisse le paiement pré-autorisé. Le code de fin attestait d'une POSSESSION ;
 * photographié ou dicté au téléphone, il permettait de facturer une intervention quittée depuis
 * longtemps.
 *
 * Six chemins clôturent une mission et tous n'ont pas de position à offrir — une clôture depuis le
 * tableau de bord web se fait derrière un bureau. Ce qui est verrouillé ici : le scan mobile
 * l'exige, une position FOURNIE est toujours vérifiée quel que soit le chemin, et toute clôture
 * repart estampillée de ce que le contrôle a conclu.
 */
class CompletionGeoProofTest extends TestCase
{
    use RefreshDatabase;

    private const SITE_LAT = 50.8467;

    private const SITE_LNG = 4.3525;

    /** ~11 km au nord : le prestataire est reparti. */
    private const FAR_LAT = 50.9467;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    /**
     * LA garantie. Sans elle, le client montre son code de fin, le prestataire s'en va, et
     * l'encaissement part depuis sa voiture — ou son domicile.
     */
    public function test_a_valid_end_code_played_from_far_away_closes_nothing(): void
    {
        [$provider, $mission, $code] = $this->scenario();

        $this->close($provider, $mission, $code, self::FAR_LAT, self::SITE_LNG)
            ->assertStatus(422)
            ->assertJsonPath('errors.position.0', fn ($m) => str_contains((string) $m, 'km'));

        $mission->refresh();
        $this->assertSame(MissionStatus::STARTED, $mission->status);
        $this->assertNull($mission->actual_end_at);
    }

    /**
     * Le code du client est ce qui coûte le plus cher à obtenir : il faut le lui redemander. Un
     * problème de position ne doit donc pas le consommer — d'autant qu'une fois revenu sur place,
     * le prestataire en aura besoin.
     */
    public function test_a_position_refusal_does_not_consume_the_end_code(): void
    {
        [$provider, $mission, $code] = $this->scenario();

        $this->close($provider, $mission, $code, self::FAR_LAT, self::SITE_LNG)->assertStatus(422);

        // Le même code clôture normalement une fois le prestataire revenu.
        $this->close($provider, $mission, $code, self::SITE_LAT, self::SITE_LNG)->assertOk();

        $this->assertSame(MissionStatus::COMPLETED, $mission->fresh()->status);
    }

    public function test_closing_on_site_records_the_measured_distance(): void
    {
        [$provider, $mission, $code] = $this->scenario();

        $this->close($provider, $mission, $code, self::SITE_LAT, self::SITE_LNG)->assertOk();

        $mission->refresh();
        $this->assertSame(OnSiteVerifier::PASSED, $mission->end_geo_verdict);
        $this->assertNotNull($mission->end_distance_m);
        $this->assertLessThan(50, $mission->end_distance_m);
    }

    public function test_the_mobile_scan_refuses_a_closure_without_position(): void
    {
        [$provider, $mission, $code] = $this->scenario();

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/complete-by-qr", ['code' => $code])
            ->assertStatus(422);

        $this->assertSame(MissionStatus::STARTED, $mission->fresh()->status);
    }

    public function test_a_mock_location_closes_nothing(): void
    {
        [$provider, $mission, $code] = $this->scenario();

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/complete-by-qr", [
                'code' => $code,
                'lat' => self::SITE_LAT,
                'lng' => self::SITE_LNG,
                'mocked' => true,
            ])
            ->assertStatus(422);

        $this->assertSame(MissionStatus::STARTED, $mission->fresh()->status);
    }

    /**
     * Le tableau de bord web n'a pas de position à offrir : l'exiger rendrait toute clôture
     * administrative impossible. Elle passe donc — mais repart estampillée, pour qu'on ne puisse
     * pas la confondre après coup avec une clôture prouvée sur place.
     */
    public function test_a_deskbound_closure_is_allowed_but_marked(): void
    {
        [$provider, $mission, $code] = $this->scenario();

        app(MissionLifecycleService::class)->validateEndCode($mission, $provider, $code);

        $mission->refresh();
        $this->assertSame(MissionStatus::COMPLETED, $mission->status);
        $this->assertSame(OnSiteVerifier::SKIPPED_NO_POSITION, $mission->end_geo_verdict);
    }

    /**
     * Garantie qui empêche le contournement : une position FOURNIE est vérifiée sur TOUS les
     * chemins. Sans cela, il suffirait de passer par un point d'entrée sans exigence pour annoncer
     * n'importe quelle position et clôturer.
     */
    public function test_a_far_position_is_refused_even_where_none_is_required(): void
    {
        [$provider, $mission, $code] = $this->scenario();

        try {
            app(MissionLifecycleService::class)
                ->validateEndCode($mission, $provider, $code, self::FAR_LAT, self::SITE_LNG);
            $this->fail('Une clôture depuis 11 km aurait dû être refusée.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('position', $e->errors());
        }

        $this->assertSame(MissionStatus::STARTED, $mission->fresh()->status);
    }

    /** Clôturer SANS code de fin reste possible — et reste soumis au même contrôle de position. */
    public function test_a_closure_without_end_code_is_also_checked(): void
    {
        [$provider, $mission] = $this->scenario();

        try {
            app(MissionLifecycleService::class)
                ->completeMission($mission, $provider, self::FAR_LAT, self::SITE_LNG);
            $this->fail('Une clôture depuis 11 km aurait dû être refusée.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('position', $e->errors());
        }

        $this->assertSame(MissionStatus::STARTED, $mission->fresh()->status);
    }

    /**
     * Une mission sans coordonnées ne doit rien exiger : il n'y a rien à comparer, et réclamer
     * quand même une position laisserait le prestataire devant la porte du client sans aucun geste
     * qui le débloque — activer sa localisation n'y changerait rien.
     */
    public function test_a_mission_without_coordinates_never_demands_a_position(): void
    {
        [$provider, $mission, $code] = $this->scenario();
        $mission->forceFill(['destination_lat' => null, 'destination_lng' => null])->save();

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/complete-by-qr", ['code' => $code])
            ->assertOk();

        $this->assertSame(
            OnSiteVerifier::SKIPPED_NO_DESTINATION,
            $mission->fresh()->end_geo_verdict,
        );
    }

    public function test_the_check_can_be_switched_off(): void
    {
        config(['trip_tracking.presence_geo_check' => false]);
        [$provider, $mission, $code] = $this->scenario();

        $this->close($provider, $mission, $code, self::FAR_LAT, self::SITE_LNG)->assertOk();

        $this->assertSame(
            OnSiteVerifier::SKIPPED_DISABLED,
            $mission->fresh()->end_geo_verdict,
        );
    }

    /**
     * @return array{0: User, 1: Mission, 2: string}
     */
    private function scenario(): array
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->create(['role' => 'employe']);

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'status' => 'sur_place',
        ]);

        $mission = Mission::query()->create([
            'booking_id' => $booking->id,
            'status' => MissionStatus::STARTED,
            'lead_provider_user_id' => $provider->id,
            'lead_employee_id' => $provider->id,
            'planned_start_at' => now()->subHours(2),
            'actual_start_at' => now()->subHour(),
            'destination_lat' => self::SITE_LAT,
            'destination_lng' => self::SITE_LNG,
        ]);

        MissionAssignment::query()->create([
            'mission_id' => $mission->id,
            'user_id' => $provider->id,
            'role' => 'lead',
            'role_on_mission' => 'lead',
            'status' => 'accepted',
            'assignment_status' => 'accepted',
            'assigned_at' => now()->subHours(2),
            'accepted_at' => now()->subHours(2),
        ]);

        // Le code passe par la porte du client, comme en vrai : le forger à la main contournerait
        // l'endroit même où il est haché.
        $code = $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/completion-code")
            ->json('data.code');

        return [$provider, $mission, $code];
    }

    private function close(User $provider, Mission $mission, string $code, float $lat, float $lng): TestResponse
    {
        return $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/complete-by-qr", [
                'code' => $code,
                'lat' => $lat,
                'lng' => $lng,
            ]);
    }
}

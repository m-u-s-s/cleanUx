<?php

namespace Tests\Feature\TripTracking;

use App\Models\Booking;
use App\Models\TripTrackingSession;
use App\Models\User;
use App\Services\TripTracking\PresenceCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * La présence se prouve par le code ET par la position, jamais par l'un des deux seul.
 *
 * Le code affiché par le client atteste d'une POSSESSION. Photographié puis envoyé par messagerie,
 * ou simplement dicté au téléphone, il se valide depuis n'importe où pendant ses dix minutes de
 * vie — c'est l'écart que ces tests referment.
 *
 * La position, elle, atteste d'une PROXIMITÉ : elle ne dit pas qu'on est entré chez le client. Ni
 * l'une ni l'autre ne suffit ; leur croisement exige d'être sur place avec le client.
 */
class PresenceGeoProofTest extends TestCase
{
    use RefreshDatabase;

    /** Le lieu de l'intervention. */
    private const SITE_LAT = 50.8467;

    private const SITE_LNG = 4.3525;

    /** ~111 m au nord : sur place, aux imprécisions près. */
    private const NEAR_LAT = 50.8477;

    /** ~11 km au nord : le prestataire est ailleurs. Chez lui, typiquement. */
    private const FAR_LAT = 50.9467;

    /** ~378 m au nord : hors du rayon de base, mais pardonnable avec un relevé imprécis. */
    private const DRIFTED_LAT = 50.8501;

    /**
     * LA garantie. Sans elle, le client photographie son écran, l'envoie au prestataire, et celui-ci
     * facture une intervention à laquelle il n'est jamais venu.
     */
    public function test_a_valid_code_played_from_far_away_is_refused(): void
    {
        [$provider, $session, $code] = $this->scenario();

        $this->confirm($provider, $session, $code, self::FAR_LAT, self::SITE_LNG)
            ->assertStatus(422)
            ->assertJsonPath('error', 'validation_failed');

        $this->assertNull($session->fresh()->presence_confirmed_at);
    }

    public function test_the_same_code_works_on_site(): void
    {
        [$provider, $session, $code] = $this->scenario();

        $this->confirm($provider, $session, $code, self::NEAR_LAT, self::SITE_LNG)->assertOk();

        $session->refresh();
        $this->assertNotNull($session->presence_confirmed_at);
        $this->assertSame(PresenceCodeService::GEO_PASSED, $session->presence_geo_verdict);
        // ~111 m : la distance est mesurée, pas devinée.
        $this->assertGreaterThan(50, $session->presence_confirmed_distance_m);
        $this->assertLessThan(200, $session->presence_confirmed_distance_m);
    }

    /**
     * Le contre-piège du dispositif : la position doit venir DU SCAN, pas du dernier relevé reçu.
     *
     * S'appuyer sur `last_lat`/`last_lng` rendrait la fraude triviale — il suffirait de couper le
     * partage de position en quittant les lieux pour figer la session sur une valeur flatteuse,
     * puis de confirmer à distance des heures plus tard.
     */
    public function test_the_session_last_ping_cannot_stand_in_for_the_scan_position(): void
    {
        [$provider, $session, $code] = $this->scenario();

        // Dernier relevé parfaitement sur place…
        $session->update(['last_lat' => self::SITE_LAT, 'last_lng' => self::SITE_LNG]);

        // …mais le scan, lui, a lieu à 11 km. C'est celui-là qui compte.
        $this->confirm($provider, $session, $code, self::FAR_LAT, self::SITE_LNG)->assertStatus(422);

        $this->assertNull($session->fresh()->presence_confirmed_at);
    }

    public function test_a_confirmation_without_any_position_is_refused(): void
    {
        [$provider, $session, $code] = $this->scenario();

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/tracking/{$session->id}/confirm-presence", ['code' => $code])
            ->assertStatus(422);

        $this->assertNull($session->fresh()->presence_confirmed_at);
    }

    /**
     * Soupape de déploiement : le temps qu'une ancienne version de l'application disparaisse, on
     * accepte une confirmation sans position — mais elle reste reconnaissable après coup.
     */
    public function test_a_missing_position_can_be_tolerated_by_configuration(): void
    {
        config(['trip_tracking.presence_require_position' => false]);
        [$provider, $session, $code] = $this->scenario();

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/tracking/{$session->id}/confirm-presence", ['code' => $code])
            ->assertOk();

        $this->assertSame(
            PresenceCodeService::GEO_SKIPPED_NO_POSITION,
            $session->fresh()->presence_geo_verdict,
        );
    }

    public function test_the_whole_check_can_be_switched_off(): void
    {
        config(['trip_tracking.presence_geo_check' => false]);
        [$provider, $session, $code] = $this->scenario();

        $this->confirm($provider, $session, $code, self::FAR_LAT, self::SITE_LNG)->assertOk();

        $this->assertSame(
            PresenceCodeService::GEO_SKIPPED_DISABLED,
            $session->fresh()->presence_geo_verdict,
        );
    }

    /**
     * Un dossier sans coordonnées ne doit pas bloquer une intervention : il n'y a rien à comparer,
     * et le prestataire n'y est pour rien. Le verdict le dit, plutôt que de laisser une distance
     * nulle raconter la même chose que trois causes différentes.
     */
    public function test_a_job_without_coordinates_does_not_block_the_provider(): void
    {
        [$provider, $session, $code] = $this->scenario();
        $session->update(['destination_lat' => null, 'destination_lng' => null]);
        $session->booking->update(['destination_lat' => null, 'destination_lng' => null]);

        $this->confirm($provider, $session, $code, self::FAR_LAT, self::SITE_LNG)->assertOk();

        $this->assertSame(
            PresenceCodeService::GEO_SKIPPED_NO_DESTINATION,
            $session->fresh()->presence_geo_verdict,
        );
    }

    /**
     * L'instantané de la session peut être vide sur les sessions ouvertes avant le remplissage des
     * coordonnées. La réservation a pu être géocodée depuis : s'en contenter neutraliserait le
     * contrôle sur toutes ces sessions.
     */
    public function test_the_booking_coordinates_are_used_when_the_session_snapshot_is_empty(): void
    {
        [$provider, $session, $code] = $this->scenario();
        $session->update(['destination_lat' => null, 'destination_lng' => null]);
        $session->booking->update([
            'destination_lat' => self::SITE_LAT,
            'destination_lng' => self::SITE_LNG,
        ]);

        $this->confirm($provider, $session, $code, self::FAR_LAT, self::SITE_LNG)->assertStatus(422);

        $this->assertNull($session->fresh()->presence_confirmed_at);
    }

    public function test_a_mock_location_is_refused(): void
    {
        [$provider, $session, $code] = $this->scenario();

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/tracking/{$session->id}/confirm-presence", [
                'code' => $code,
                'lat' => self::SITE_LAT,
                'lng' => self::SITE_LNG,
                'mocked' => true,
            ])
            ->assertStatus(422);

        $this->assertNull($session->fresh()->presence_confirmed_at);
    }

    /**
     * Un relevé honnête mais mauvais mérite d'être jugé sur la précision qu'il annonce. À 378 m, le
     * rayon de base (250 m) refuserait — la précision annoncée de 400 m rattrape l'écart.
     */
    public function test_a_poor_but_declared_accuracy_widens_the_accepted_radius(): void
    {
        [$provider, $session, $code] = $this->scenario();

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/tracking/{$session->id}/confirm-presence", [
                'code' => $code,
                'lat' => self::DRIFTED_LAT,
                'lng' => self::SITE_LNG,
                'accuracy_m' => 400,
            ])
            ->assertOk();
    }

    /** Sans la précision annoncée, la même position est hors du rayon : l'élargissement est bien ce qui l'a sauvée. */
    public function test_the_same_drifted_position_is_refused_without_the_declared_accuracy(): void
    {
        [$provider, $session, $code] = $this->scenario();

        $this->confirm($provider, $session, $code, self::DRIFTED_LAT, self::SITE_LNG)
            ->assertStatus(422);
    }

    /**
     * La précision vient de l'appareil, donc de la partie contrôlée. Sans plafond, en annoncer une
     * énorme rouvrirait la porte en grand.
     */
    public function test_an_absurd_declared_accuracy_is_capped(): void
    {
        [$provider, $session, $code] = $this->scenario();

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/tracking/{$session->id}/confirm-presence", [
                'code' => $code,
                'lat' => self::FAR_LAT,
                'lng' => self::SITE_LNG,
                'accuracy_m' => 50000,
            ])
            ->assertStatus(422);

        $this->assertNull($session->fresh()->presence_confirmed_at);
    }

    /**
     * Être au mauvais endroit n'est pas se tromper de code. Consommer un essai ici priverait le
     * prestataire dont le relevé dérive des tentatives dont il aura besoin une fois sur place.
     */
    public function test_a_position_refusal_does_not_burn_a_code_attempt(): void
    {
        [$provider, $session, $code] = $this->scenario();

        for ($i = 0; $i < PresenceCodeService::MAX_ATTEMPTS + 3; $i++) {
            $this->confirm($provider, $session, $code, self::FAR_LAT, self::SITE_LNG)->assertStatus(422);
        }

        $this->assertSame(0, $session->fresh()->presence_code_attempts);

        // Le code est intact : une fois arrivé, le prestataire confirme normalement.
        $this->confirm($provider, $session, $code, self::SITE_LAT, self::SITE_LNG)->assertOk();
    }

    /** Une tentative depuis 11 km est exactement ce qu'une revue de fraude cherche. */
    public function test_a_refused_attempt_is_kept_for_review(): void
    {
        [$provider, $session, $code] = $this->scenario();

        $this->confirm($provider, $session, $code, self::FAR_LAT, self::SITE_LNG)->assertStatus(422);

        $rejections = $session->fresh()->metadata['presence_geo_rejections'] ?? [];
        $this->assertCount(1, $rejections);
        $this->assertEqualsWithDelta(self::FAR_LAT, $rejections[0]['lat'], 0.0001);
        $this->assertGreaterThan(10000, $rejections[0]['distance_m']);
    }

    /** Une série d'essais ne doit pas gonfler la ligne sans fin. */
    public function test_the_kept_rejections_are_capped(): void
    {
        [$provider, $session, $code] = $this->scenario();

        for ($i = 0; $i < 15; $i++) {
            $this->confirm($provider, $session, $code, self::FAR_LAT, self::SITE_LNG)->assertStatus(422);
        }

        $this->assertCount(10, $session->fresh()->metadata['presence_geo_rejections']);
    }

    /**
     * Les colonnes `presence_confirmed_*` disent que la présence a été établie. Y écrire un essai
     * repoussé ferait mentir la ligne — et un litige se plaide sur cette ligne.
     */
    public function test_a_refused_attempt_never_pollutes_the_confirmation_columns(): void
    {
        [$provider, $session, $code] = $this->scenario();

        $this->confirm($provider, $session, $code, self::FAR_LAT, self::SITE_LNG)->assertStatus(422);

        $session->refresh();
        $this->assertNull($session->presence_confirmed_lat);
        $this->assertNull($session->presence_confirmed_distance_m);
        $this->assertNull($session->presence_geo_verdict);
    }

    /**
     * Un double scan reste sans conséquence : la présence est un fait déjà gravé, et le réseau
     * mobile fait rejouer des appels. La rejuger sur la position d'un prestataire qui a depuis
     * repris la route effacerait une preuve valide.
     */
    public function test_an_already_confirmed_presence_is_not_re_examined(): void
    {
        [$provider, $session, $code] = $this->scenario();

        $this->confirm($provider, $session, $code, self::SITE_LAT, self::SITE_LNG)->assertOk();
        $confirmedAt = $session->fresh()->presence_confirmed_at;

        $this->confirm($provider, $session, $code, self::FAR_LAT, self::SITE_LNG)->assertOk();

        $this->assertEquals($confirmedAt, $session->fresh()->presence_confirmed_at);
    }

    /**
     * @return array{0: User, 1: TripTrackingSession, 2: string}
     */
    private function scenario(): array
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->create(['role' => 'employe']);

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'status' => 'sur_place',
        ]);

        $session = TripTrackingSession::query()->create([
            'code' => TripTrackingSession::generateCode(),
            'booking_id' => $booking->id,
            'provider_user_id' => $provider->id,
            'status' => TripTrackingSession::STATUS_IN_MISSION,
            'destination_lat' => self::SITE_LAT,
            'destination_lng' => self::SITE_LNG,
            'started_at' => now()->subMinutes(20),
        ]);

        // Le code passe par la porte du client, comme en vrai : le forger à la main contournerait
        // l'endroit même où il est haché.
        $code = $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/presence-code")
            ->json('data.code');

        return [$provider, $session, $code];
    }

    /**
     * @return TestResponse
     */
    private function confirm(User $provider, TripTrackingSession $session, string $code, float $lat, float $lng)
    {
        return $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/tracking/{$session->id}/confirm-presence", [
                'code' => $code,
                'lat' => $lat,
                'lng' => $lng,
            ]);
    }
}

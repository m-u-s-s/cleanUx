<?php

namespace Tests\Feature\Trajet;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Cancellation\CancellationFeeCalculator;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/** LE CLIENT NE VIENT PAS — ce que le conducteur peut faire, et depuis quand. */
class ClientAbsentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array{0: User, 1: Mission, 2: Booking}
     */
    private function course(bool $arrive, bool $immediate = true): array
    {
        $client = User::factory()->client()->create();
        $prestataire = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $prestataire->id, 'status' => 'active']);

        $booking = Booking::create(array_filter([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'client_id' => $client->id,
            'customer_user_id' => $client->id,
            'status' => BookingStatus::SUR_PLACE,
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => $immediate ? 'asap' : 'scheduled',
            'estimated_price' => 30,
            'destination_lat' => 50.8467,
            'destination_lng' => 4.3525,
            'dropoff_lat' => 50.9010,
            'dropoff_lng' => 4.4844,
            // Une commande immédiate n'a PAS d'horaire : c'est tout le problème d'origine.
            'scheduled_date' => $immediate ? null : now()->subHour()->toDateString(),
            'scheduled_time' => $immediate ? null : now()->subHour()->format('H:i:s'),
        ], fn ($v) => $v !== null));

        $mission = Mission::create([
            'booking_id' => $booking->id,
            'lead_provider_user_id' => $prestataire->id,
            'status' => MissionStatus::ARRIVED,
        ]);

        MissionAssignment::factory()->accepted()->create([
            'mission_id' => $mission->id,
            'user_id' => $prestataire->id,
            'arrived_at' => $arrive ? now() : null,
        ]);

        return [$prestataire, $mission, $booking];
    }

    public function test_l_absence_n_est_pas_declarable_pendant_l_attente(): void
    {
        [, , $booking] = $this->course(arrive: true);

        $this->assertFalse(app(CancellationFeeCalculator::class)->isNoShow($booking));
    }

    public function test_l_absence_devient_declarable_apres_cinq_minutes(): void
    {
        [, , $booking] = $this->course(arrive: true);

        Carbon::setTestNow(now()->addMinutes(6));

        $this->assertTrue(
            app(CancellationFeeCalculator::class)->isNoShow($booking->fresh()),
            'Sans ce chemin, une course immédiate ne pouvait JAMAIS déclarer une absence : le délai se comptait depuis un horaire inexistant.'
        );
    }

    public function test_sans_arrivee_declaree_rien_ne_court(): void
    {
        [, , $booking] = $this->course(arrive: false);

        Carbon::setTestNow(now()->addHour());

        $this->assertFalse(
            app(CancellationFeeCalculator::class)->isNoShow($booking->fresh()),
            'Le décompte part de l’arrivée : sans elle, il n’a pas d’origine, et une absence déclarée depuis chez soi serait indéfendable.'
        );
    }

    /** LE TÉMOIN : sur une intervention ordinaire, le délai reste celui de l'horaire prévu. */
    public function test_une_intervention_ordinaire_garde_son_delai_de_quinze_minutes(): void
    {
        $client = User::factory()->client()->create();

        // LE JOUR ET L'HEURE VIENNENT DU MEME INSTANT, ET CE N'EST PAS UN DETAIL DE STYLE.
        $prevu = now()->subMinutes(10);

        $booking = Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'client_id' => $client->id,
            'status' => BookingStatus::CONFIRME,
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
            'scheduled_date' => $prevu->toDateString(),
            'scheduled_time' => $prevu->format('H:i:s'),
        ]);

        $calculateur = app(CancellationFeeCalculator::class);

        // Dix minutes après l'heure prévue : la grâce de quinze minutes court encore.
        $this->assertFalse($calculateur->isNoShow($booking));

        Carbon::setTestNow(now()->addMinutes(6));
        $this->assertTrue($calculateur->isNoShow($booking->fresh()));
    }

    public function test_l_api_cloture_la_course_sur_absence_du_client(): void
    {
        Notification::fake();
        [$prestataire, $mission, $booking] = $this->course(arrive: true);

        Carbon::setTestNow(now()->addMinutes(6));

        $this->actingAs($prestataire)
            ->postJson("/api/provider/missions/{$mission->id}/no-show")
            ->assertOk()
            ->assertJsonPath('type', 'client_no_show');

        $this->assertSame(BookingStatus::ANNULE, $booking->fresh()->status);
    }

    public function test_l_api_refuse_l_absence_avant_l_attente(): void
    {
        Notification::fake();
        [$prestataire, $mission] = $this->course(arrive: true);

        $this->actingAs($prestataire)
            ->postJson("/api/provider/missions/{$mission->id}/no-show")
            ->assertStatus(409);
    }

    public function test_le_detail_de_mission_annonce_l_echeance(): void
    {
        [$prestataire, $mission] = $this->course(arrive: true);

        $reponse = $this->actingAs($prestataire)
            ->getJson("/api/provider/missions/{$mission->id}")
            ->assertOk();

        $this->assertTrue($reponse->json('data.is_ride'));
        $this->assertNotNull(
            $reponse->json('data.no_show_available_at'),
            'Une DATE, pas une durée : un décompte en secondes se remettrait à zéro à chaque retour sur l’écran.'
        );
    }
}

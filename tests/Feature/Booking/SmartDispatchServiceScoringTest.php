<?php

namespace Tests\Feature\Booking;

use App\Enums\ProviderType;
use App\Models\Booking;
use App\Models\Disponibilite;
use App\Models\ProviderProfile;
use App\Models\Trade;
use App\Models\User;
use App\Services\Booking\SmartDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/** Couvre le moteur de scoring de SmartDispatchService : score() et ses sous-scores (zone/qualité/charge/premium/asap/distance), assignBestEmployee() (happy + garde), explainScores() et explainBestMatch(). */
class SmartDispatchServiceScoringTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    /**
     * `$tradeId` : `explainScores()` ne retient que les prestataires DU métier demandé.
     * Sans lui, l’employé reste éligible à la zone mais n’apparaît dans aucun classement.
     */
    private function eligibleEmployee(int $zoneId, string $date, array $userOverrides = [], ?int $tradeId = null): User
    {
        $user = User::factory()->create(array_merge([
            'role' => User::ROLE_EMPLOYE,
            'is_active' => true,
            'primary_service_zone_id' => $zoneId,
        ], $userOverrides));

        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => ProviderType::INDEPENDENT->value,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        Disponibilite::create([
            'user_id' => $user->id,
            'date' => $date,
            'heure_debut' => '08:00:00',
            'heure_fin' => '18:00:00',
        ]);

        if ($tradeId !== null) {
            $user->trades()->syncWithoutDetaching([$tradeId]);
        }

        return $user;
    }

    private function service(): SmartDispatchService
    {
        return app(SmartDispatchService::class);
    }

    public function test_score_rewards_idle_primary_zone_employee_for_scheduled_booking(): void
    {
        $context = $this->createCoverageContext();
        $zoneId = $context['zone']->id;
        $date = now()->addDays(3)->toDateString();

        $metier = Trade::factory()->create();
        $employee = $this->eligibleEmployee($zoneId, $date, [], $metier->id);
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => null,
            'service_zone_id' => $zoneId,
            'date' => $date,
            'heure' => '10:00:00',
            'duree' => 90,
            'estimated_duration_minutes' => 90,
            'booking_mode' => 'scheduled',
            'status' => 'en_attente',
            'trade_id' => $metier->id,
        ]);

        // zone 500 + favori 0 + qualité 100 + charge 250 + premium 0 + asap 0 + distance 0
        $this->assertSame(850, $this->service()->score($employee, $booking->fresh()));
    }

    public function test_score_adds_asap_and_premium_bonuses(): void
    {
        $context = $this->createCoverageContext();
        $zoneId = $context['zone']->id;
        $date = now()->toDateString();

        $metier = Trade::factory()->create();
        $employee = $this->eligibleEmployee($zoneId, $date, [], $metier->id);

        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
            'plan_type' => 'premium',
            'plan_status' => 'active',
        ]);

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => null,
            'service_zone_id' => $zoneId,
            'date' => $date,
            'heure' => '10:00:00',
            'duree' => 90,
            'estimated_duration_minutes' => 90,
            'booking_mode' => 'asap',
            'status' => 'en_attente',
            'trade_id' => $metier->id,
        ]);

        // zone 500 + qualité 100 + charge 250 + premium 80 + asap (200+150) + distance 0
        $this->assertSame(1280, $this->service()->score($employee, $booking->fresh()));
    }

    public function test_assign_best_employee_returns_eligible_available_provider(): void
    {
        $context = $this->createCoverageContext();
        $zoneId = $context['zone']->id;
        $date = now()->addDays(3)->toDateString();

        $metier = Trade::factory()->create();
        $employee = $this->eligibleEmployee($zoneId, $date, [], $metier->id);
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => null,
            'service_zone_id' => $zoneId,
            'date' => $date,
            'heure' => '10:00:00',
            'duree' => 90,
            'estimated_duration_minutes' => 90,
            'booking_mode' => 'scheduled',
            'status' => 'en_attente',
            'trade_id' => $metier->id,
        ]);

        $best = $this->service()->assignBestEmployee($booking->fresh());

        $this->assertNotNull($best);
        $this->assertSame($employee->id, $best->id);
    }

    public function test_assign_best_employee_returns_null_without_zone_or_slot(): void
    {
        $booking = new Booking;
        $booking->service_zone_id = null;
        $booking->date = now()->addDay();
        $booking->heure = '10:00:00';

        $this->assertNull($this->service()->assignBestEmployee($booking));
    }

    public function test_explain_scores_ranks_candidates_and_exposes_distance(): void
    {
        $context = $this->createCoverageContext();
        $zoneId = $context['zone']->id;
        $date = now()->addDays(3)->toDateString();

        $metier = Trade::factory()->create();
        $employee = $this->eligibleEmployee($zoneId, $date, [], $metier->id);
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => null,
            'service_zone_id' => $zoneId,
            'date' => $date,
            'heure' => '10:00:00',
            'duree' => 90,
            'estimated_duration_minutes' => 90,
            'booking_mode' => 'scheduled',
            'status' => 'en_attente',
            'trade_id' => $metier->id,
        ]);

        $explained = $this->service()->explainScores($booking->fresh());

        $this->assertInstanceOf(Collection::class, $explained);
        $this->assertCount(1, $explained);

        $row = $explained->first();
        $this->assertSame($employee->id, $row['employee_id']);
        $this->assertSame($employee->name, $row['name']);
        $this->assertIsInt($row['score']);
        $this->assertTrue($row['available']);
        // Pas de colonne current_lat sur users dans ce schéma → distance non calculable.
        $this->assertNull($row['distance_km']);
    }

    public function test_explain_scores_returns_empty_collection_without_zone(): void
    {
        $booking = new Booking;
        $booking->service_zone_id = null;

        $explained = $this->service()->explainScores($booking);

        $this->assertInstanceOf(Collection::class, $explained);
        $this->assertTrue($explained->isEmpty());
    }

    public function test_explain_best_match_summarises_selection(): void
    {
        $context = $this->createCoverageContext();
        $zoneId = $context['zone']->id;
        $date = now()->addDays(3)->toDateString();

        $metier = Trade::factory()->create();
        $employee = $this->eligibleEmployee($zoneId, $date, [], $metier->id);
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => $employee->id,
            'service_zone_id' => $zoneId,
            'date' => $date,
            'heure' => '10:00:00',
            'duree' => 90,
            'estimated_duration_minutes' => 90,
            'booking_mode' => 'scheduled',
            'status' => 'en_attente',
            'trade_id' => $metier->id,
        ]);

        // LE CLASSEMENT NE RETIENT QUE LES LIBRES : l'employe deja pose sur CE rendez-vous ne
        // s'y propose plus. On ajoute donc un second prestataire du meme metier, lui vraiment
        // libre, pour que le resume ait quelque chose de reel a resumer.
        $libre = $this->eligibleEmployee($zoneId, $date, [], $metier->id);

        $summary = $this->service()->explainBestMatch($booking->fresh());

        $this->assertSame($booking->id, $summary['booking_id']);
        $this->assertSame('scheduled', $summary['booking_mode']);
        $this->assertSame($employee->id, $summary['selected_employee_id']);
        $this->assertSame(1, $summary['candidates_count']);
        $this->assertIsArray($summary['candidates']);
        $this->assertCount(1, $summary['candidates']);
        $this->assertSame($libre->id, $summary['candidates'][0]['employee_id']);
    }
}

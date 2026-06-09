<?php

namespace Tests\Feature\Booking;

use App\Enums\ProviderType;
use App\Models\Booking;
use App\Models\Disponibilite;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Booking\SmartDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/**
 * Couvre les branches encore non exercées de SmartDispatchService :
 * - workloadScore aux paliers 1 / 2 / 3+ missions le même jour ;
 * - asapScore quand l'employé porte déjà >= 3 missions du jour (pénalité) ;
 * - assignBestEmployee via le prestataire préféré (assigned puis unavailable) ;
 * - premiumScore + favoriteScore quand la réservation n'a pas de client.
 *
 * Scoring déterministe hors charge : employé sur sa zone primaire (zone 500),
 * aucune mission notée (qualité 100), pas de table client_provider_preferences
 * (favori 0), pas de coordonnées employé (distance 0).
 */
class SmartDispatchServiceCoverageBatch18Test extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    private function dispatcher(): SmartDispatchService
    {
        return app(SmartDispatchService::class);
    }

    private function primaryZoneEmployee(int $zoneId): User
    {
        return User::factory()->create([
            'role' => User::ROLE_EMPLOYE,
            'is_active' => true,
            'primary_service_zone_id' => $zoneId,
        ]);
    }

    private function eligibleEmployee(int $zoneId, string $date): User
    {
        $user = $this->primaryZoneEmployee($zoneId);

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

        return $user;
    }

    private function makeBooking(int $zoneId, ?int $clientId, string $date, string $mode = 'scheduled', array $overrides = []): Booking
    {
        return Booking::factory()->create(array_merge([
            'client_id' => $clientId,
            'employe_id' => null,
            'service_zone_id' => $zoneId,
            'date' => $date,
            'heure' => '10:00:00',
            'duree' => 90,
            'duree_estimee' => 90,
            'booking_mode' => $mode,
            'status' => 'en_attente',
        ], $overrides))->fresh();
    }

    private function seedEmployeeMissions(User $employee, string $date, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            DB::table('rendez_vous')->insert([
                'employe_id' => $employee->id,
                'date' => $date,
                'heure' => '08:00',
                'status' => 'confirme',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_workload_score_penalises_progressively_with_same_day_missions(): void
    {
        $context = $this->createCoverageContext();
        $zoneId = $context['zone']->id;
        $date = now()->addDays(3)->toDateString();

        $employee = $this->primaryZoneEmployee($zoneId);
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $booking = $this->makeBooking($zoneId, $client->id, $date);

        // zone 500 + qualité 100 + charge (1 mission → 150)
        $this->seedEmployeeMissions($employee, $date, 1);
        $this->assertSame(750, $this->dispatcher()->score($employee->fresh(), $booking));

        // charge (2 missions → 60)
        $this->seedEmployeeMissions($employee, $date, 1);
        $this->assertSame(660, $this->dispatcher()->score($employee->fresh(), $booking));

        // charge (3 missions → -150)
        $this->seedEmployeeMissions($employee, $date, 1);
        $this->assertSame(450, $this->dispatcher()->score($employee->fresh(), $booking));
    }

    public function test_asap_score_drops_when_employee_already_has_three_missions_today(): void
    {
        $context = $this->createCoverageContext();
        $zoneId = $context['zone']->id;
        $date = now()->toDateString();

        $employee = $this->primaryZoneEmployee($zoneId);
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $booking = $this->makeBooking($zoneId, $client->id, $date, 'asap');

        $this->seedEmployeeMissions($employee, $date, 3);

        // zone 500 + qualité 100 + charge (-150) + asap (200 - 200 = 0)
        $this->assertSame(450, $this->dispatcher()->score($employee->fresh(), $booking));
    }

    public function test_assign_best_employee_honours_available_preferred_provider(): void
    {
        $context = $this->createCoverageContext();
        $zoneId = $context['zone']->id;
        $date = now()->addDays(3)->toDateString();

        $preferred = $this->eligibleEmployee($zoneId, $date);
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $booking = $this->makeBooking($zoneId, $client->id, $date, 'scheduled', [
            'preferred_provider_user_id' => $preferred->id,
        ]);

        $best = $this->dispatcher()->assignBestEmployee($booking);

        $this->assertNotNull($best);
        $this->assertSame($preferred->id, $best->id);
    }

    public function test_assign_best_employee_returns_null_when_preferred_provider_unavailable(): void
    {
        $context = $this->createCoverageContext();
        $zoneId = $context['zone']->id;
        $date = now()->addDays(3)->toDateString();

        // Prestataire déjà occupé sur ce créneau → le résolveur le marque "unavailable".
        $preferred = $this->primaryZoneEmployee($zoneId);
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $this->makeBooking($zoneId, $client->id, $date, 'scheduled', [
            'employe_id' => $preferred->id,
            'status' => 'confirme',
        ]);

        $booking = $this->makeBooking($zoneId, $client->id, $date, 'scheduled', [
            'preferred_provider_user_id' => $preferred->id,
        ]);

        $this->assertNull($this->dispatcher()->assignBestEmployee($booking));
    }

    public function test_score_handles_booking_without_client_for_premium_and_favorite(): void
    {
        $context = $this->createCoverageContext();
        $zoneId = $context['zone']->id;
        $date = now()->addDays(3)->toDateString();

        $employee = $this->primaryZoneEmployee($zoneId);
        $booking = $this->makeBooking($zoneId, null, $date);

        // zone 500 + favori 0 (pas de client) + qualité 100 + charge 250 + premium 0 (pas de client)
        $this->assertSame(850, $this->dispatcher()->score($employee->fresh(), $booking));
    }
}

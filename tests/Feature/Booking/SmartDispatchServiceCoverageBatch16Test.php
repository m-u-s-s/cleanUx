<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\EmployeeZoneAssignment;
use App\Models\User;
use App\Services\Booking\SmartDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/** Cible les branches non couvertes du scoring de SmartDispatchService : zoneScore via les affectations employee_zone_assignments (primary/secondary/ backup/inconnu) et l'absence d'affectation, plus la charge de travail (workloadScore) à partir de rendez_vous existants. */
class SmartDispatchServiceCoverageBatch16Test extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    private function dispatcher(): SmartDispatchService
    {
        return app(SmartDispatchService::class);
    }

    private function scheduledBooking(int $zoneId, ?int $clientId = null): Booking
    {
        $clientId ??= User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ])->id;

        return Booking::factory()->create([
            'client_id' => $clientId,
            'employe_id' => null,
            'service_zone_id' => $zoneId,
            'date' => now()->addDays(3)->toDateString(),
            'heure' => '10:00:00',
            'duree' => 90,
            'duree_estimee' => 90,
            'booking_mode' => 'scheduled',
            'status' => 'en_attente',
        ])->fresh();
    }

    private function nonPrimaryEmployee(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_EMPLOYE,
            'is_active' => true,
            'primary_service_zone_id' => null,
        ]);
    }

    /**
     * @return iterable<string, array{0: string, 1: int}>
     */
    public static function assignmentTypeProvider(): iterable
    {
        // zoneScore + qualité 100 + charge 250 (= 350 hors zone)
        yield 'primary assignment' => ['primary', 400 + 350];
        yield 'secondary assignment' => ['secondary', 250 + 350];
        yield 'backup assignment' => ['backup', 120 + 350];
        yield 'unknown assignment type' => ['temporary', 80 + 350];
    }

    #[DataProvider('assignmentTypeProvider')]
    public function test_zone_score_uses_assignment_type_when_not_primary_zone(string $assignmentType, int $expectedScore): void
    {
        $context = $this->createCoverageContext();
        $zoneId = $context['zone']->id;

        $employee = $this->nonPrimaryEmployee();

        EmployeeZoneAssignment::create([
            'user_id' => $employee->id,
            'service_zone_id' => $zoneId,
            'assignment_type' => $assignmentType,
            'coverage_priority' => 10,
            'is_active' => true,
            'starts_at' => now()->subDay(),
        ]);

        $booking = $this->scheduledBooking($zoneId);

        $this->assertSame($expectedScore, $this->dispatcher()->score($employee->fresh(), $booking));
    }

    public function test_zone_score_is_zero_without_any_assignment(): void
    {
        $context = $this->createCoverageContext();
        $zoneId = $context['zone']->id;

        $employee = $this->nonPrimaryEmployee();
        $booking = $this->scheduledBooking($zoneId);

        // zone 0 + qualité 100 + charge 250 = 350
        $this->assertSame(350, $this->dispatcher()->score($employee->fresh(), $booking));
    }
}

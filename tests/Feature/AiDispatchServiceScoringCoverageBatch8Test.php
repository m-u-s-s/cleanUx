<?php

namespace Tests\Feature;

use App\Enums\ProviderType;
use App\Models\Booking;
use App\Models\EmployeeZoneAssignment;
use App\Models\ProviderProfile;
use App\Models\ServiceZone;
use App\Models\User;
use App\Services\Dispatch\AiDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AiDispatchServiceScoringCoverageBatch8Test extends TestCase
{
    use RefreshDatabase;

    protected function makeEmployee(?int $zoneId = null, bool $online = true): User
    {
        $user = User::factory()->employe()->create([
            'is_active' => true,
            'primary_service_zone_id' => $zoneId,
        ]);

        ProviderProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'provider_type' => ProviderType::INDEPENDENT->value,
                'status' => 'active',
                'verification_status' => 'verified',
                'is_online' => $online,
            ],
        );

        return $user;
    }

    protected function makeClient(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ], $overrides));
    }

    protected function makeBooking(ServiceZone $zone, User $client, array $overrides = []): Booking
    {
        return Booking::factory()->create(array_merge([
            'client_id' => $client->id,
            'service_zone_id' => $zone->id,
            'date' => now()->addDay()->toDateString(),
            'heure' => '10:00',
            'duree_estimee' => 90,
            'status' => 'en_attente',
        ], $overrides));
    }

    private function service(): AiDispatchService
    {
        return app(AiDispatchService::class);
    }

    public function test_secondary_zone_assignment_yields_secondary_score(): void
    {
        $zone = ServiceZone::factory()->create();
        $employee = $this->makeEmployee(null); // pas de zone primaire
        $client = $this->makeClient();

        EmployeeZoneAssignment::factory()->create([
            'user_id' => $employee->id,
            'service_zone_id' => $zone->id,
            'assignment_type' => 'secondary',
        ]);

        $rdv = $this->makeBooking($zone, $client)->fresh(['client', 'serviceZone']);

        $details = $this->service()->scoreDetails($employee->fresh(), $rdv);
        $this->assertSame(150, $details['zone']);
    }

    public function test_backup_zone_assignment_yields_backup_score(): void
    {
        $zone = ServiceZone::factory()->create();
        $employee = $this->makeEmployee(null);
        $client = $this->makeClient();

        EmployeeZoneAssignment::factory()->create([
            'user_id' => $employee->id,
            'service_zone_id' => $zone->id,
            'assignment_type' => 'backup',
        ]);

        $rdv = $this->makeBooking($zone, $client)->fresh(['client', 'serviceZone']);

        $details = $this->service()->scoreDetails($employee->fresh(), $rdv);
        $this->assertSame(80, $details['zone']);
    }

    public function test_primary_zone_assignment_yields_assignment_primary_score(): void
    {
        $zone = ServiceZone::factory()->create();
        // Pas de primary_service_zone_id : on passe par la table d'assignation.
        $employee = $this->makeEmployee(null);
        $client = $this->makeClient();

        EmployeeZoneAssignment::factory()->create([
            'user_id' => $employee->id,
            'service_zone_id' => $zone->id,
            'assignment_type' => 'primary',
        ]);

        $rdv = $this->makeBooking($zone, $client)->fresh(['client', 'serviceZone']);

        $details = $this->service()->scoreDetails($employee->fresh(), $rdv);
        $this->assertSame(250, $details['zone']);
    }

    public function test_workload_score_decreases_with_same_day_bookings(): void
    {
        $zone = ServiceZone::factory()->create();
        $employee = $this->makeEmployee($zone->id);
        $client = $this->makeClient();

        $rdv = $this->makeBooking($zone, $client)->fresh(['client', 'serviceZone']);
        $service = $this->service();

        // 0 mission → 220 (palier max)
        $this->assertSame(220, $service->scoreDetails($employee->fresh(), $rdv)['workload']);

        $this->seedEmployeeRendezVous($employee, (string) $rdv->date, 1);
        $this->assertSame(140, $service->scoreDetails($employee->fresh(), $rdv)['workload']);

        $this->seedEmployeeRendezVous($employee, (string) $rdv->date, 1);
        $this->assertSame(50, $service->scoreDetails($employee->fresh(), $rdv)['workload']);

        $this->seedEmployeeRendezVous($employee, (string) $rdv->date, 1);
        $this->assertSame(-200, $service->scoreDetails($employee->fresh(), $rdv)['workload']);
    }

    private function seedEmployeeRendezVous(User $employee, string $date, int $count): void
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

    public function test_premium_client_adds_premium_bonus(): void
    {
        $zone = ServiceZone::factory()->create();
        $employee = $this->makeEmployee($zone->id);

        $premiumClient = $this->makeClient([
            'plan_type' => 'premium',
            'plan_status' => 'active',
        ]);
        $standardClient = $this->makeClient([
            'plan_type' => 'standard',
            'plan_status' => 'active',
        ]);

        $premiumRdv = $this->makeBooking($zone, $premiumClient)->fresh(['client', 'serviceZone']);
        $standardRdv = $this->makeBooking($zone, $standardClient)->fresh(['client', 'serviceZone']);

        $service = $this->service();
        $this->assertSame(80, $service->scoreDetails($employee->fresh(), $premiumRdv)['premium']);
        $this->assertSame(0, $service->scoreDetails($employee->fresh(), $standardRdv)['premium']);
    }

    public function test_shadow_mode_logs_comparison_and_returns_candidate(): void
    {
        config([
            'matching.enabled' => true,
            'matching.shadow_mode' => true,
        ]);

        Log::spy();

        $zone = ServiceZone::factory()->create();
        $employee = $this->makeEmployee($zone->id);
        $client = $this->makeClient();
        $rdv = $this->makeBooking($zone, $client)->fresh(['client', 'serviceZone']);

        $best = $this->service()->bestEmployeeFor($rdv);

        $this->assertNotNull($best);
        $this->assertSame($employee->id, $best->id);
    }
}

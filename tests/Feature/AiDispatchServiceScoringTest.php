<?php

namespace Tests\Feature;

use App\Enums\ProviderType;
use App\Models\Booking;
use App\Models\ProviderProfile;
use App\Models\ServiceZone;
use App\Models\User;
use App\Services\Dispatch\AiDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiDispatchServiceScoringTest extends TestCase
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

    protected function makeClient(): User
    {
        return User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
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

    public function test_score_equals_sum_of_score_details(): void
    {
        $zone = ServiceZone::factory()->create();
        $employee = $this->makeEmployee($zone->id);
        $client = $this->makeClient();
        $rdv = $this->makeBooking($zone, $client)->fresh(['client', 'serviceZone']);

        $service = app(AiDispatchService::class);
        $details = $service->scoreDetails($employee->fresh(), $rdv);
        $score = $service->score($employee->fresh(), $rdv);

        $this->assertSame(
            ['zone', 'quality', 'workload', 'favorite', 'premium', 'urgency', 'reliability'],
            array_keys($details)
        );
        $this->assertSame(array_sum($details), $score);
    }

    public function test_primary_zone_match_yields_top_zone_score(): void
    {
        $zone = ServiceZone::factory()->create();
        $employee = $this->makeEmployee($zone->id);
        $client = $this->makeClient();
        $rdv = $this->makeBooking($zone, $client)->fresh(['client', 'serviceZone']);

        $details = app(AiDispatchService::class)->scoreDetails($employee->fresh(), $rdv);

        // primary_service_zone_id === service_zone_id → 300
        $this->assertSame(300, $details['zone']);
    }

    public function test_no_zone_match_yields_zero_zone_score(): void
    {
        $zoneA = ServiceZone::factory()->create();
        $zoneB = ServiceZone::factory()->create();
        $employee = $this->makeEmployee($zoneB->id); // primary is a different zone
        $client = $this->makeClient();
        $rdv = $this->makeBooking($zoneA, $client)->fresh(['client', 'serviceZone']);

        $details = app(AiDispatchService::class)->scoreDetails($employee->fresh(), $rdv);

        $this->assertSame(0, $details['zone']);
    }

    public function test_urgent_booking_adds_urgency_bonus(): void
    {
        $zone = ServiceZone::factory()->create();
        $employee = $this->makeEmployee($zone->id);
        $client = $this->makeClient();

        $urgent = $this->makeBooking($zone, $client, ['priorite' => 'urgente'])
            ->fresh(['client', 'serviceZone']);
        $normal = $this->makeBooking($zone, $client, ['priorite' => 'normale'])
            ->fresh(['client', 'serviceZone']);

        $service = app(AiDispatchService::class);
        $this->assertSame(120, $service->scoreDetails($employee->fresh(), $urgent)['urgency']);
        $this->assertSame(0, $service->scoreDetails($employee->fresh(), $normal)['urgency']);
    }

    public function test_idle_employee_gets_full_workload_and_reliability_defaults(): void
    {
        $zone = ServiceZone::factory()->create();
        $employee = $this->makeEmployee($zone->id);
        $client = $this->makeClient();
        $rdv = $this->makeBooking($zone, $client)->fresh(['client', 'serviceZone']);

        $details = app(AiDispatchService::class)->scoreDetails($employee->fresh(), $rdv);

        // Aucune mission ce jour → workload max 220.
        $this->assertSame(220, $details['workload']);
        // Aucune lead mission → reliability défaut 100.
        $this->assertSame(100, $details['reliability']);
        // Pas de favori configuré → 0.
        $this->assertSame(0, $details['favorite']);
        // quality défaut 120 (pas de missions / colonne absente).
        $this->assertSame(120, $details['quality']);
    }

    public function test_rank_employees_returns_empty_without_zone(): void
    {
        $client = $this->makeClient();
        $rdv = Booking::factory()->create([
            'client_id' => $client->id,
            'service_zone_id' => null,
            'date' => now()->addDay()->toDateString(),
            'heure' => '10:00',
        ]);

        $ranking = app(AiDispatchService::class)->rankEmployees($rdv->fresh());
        $this->assertCount(0, $ranking);
    }

    public function test_rank_employees_excludes_offline_provider_for_asap(): void
    {
        $zone = ServiceZone::factory()->create();
        $online = $this->makeEmployee($zone->id, online: true);
        $offline = $this->makeEmployee($zone->id, online: false);
        $client = $this->makeClient();

        $rdv = $this->makeBooking($zone, $client, ['booking_mode' => 'asap'])
            ->fresh(['client', 'serviceZone']);

        $ranking = app(AiDispatchService::class)->rankEmployees($rdv);
        $ids = $ranking->pluck('employee.id')->all();

        $this->assertContains($online->id, $ids);
        $this->assertNotContains($offline->id, $ids);
    }

    public function test_best_employee_for_returns_top_candidate_with_matching_disabled(): void
    {
        config(['matching.enabled' => false]);

        $zone = ServiceZone::factory()->create();
        $employee = $this->makeEmployee($zone->id);
        $client = $this->makeClient();
        $rdv = $this->makeBooking($zone, $client)->fresh(['client', 'serviceZone']);

        $best = app(AiDispatchService::class)->bestEmployeeFor($rdv);

        $this->assertNotNull($best);
        $this->assertSame($employee->id, $best->id);
    }

    public function test_best_employee_for_returns_null_when_no_candidates(): void
    {
        config(['matching.enabled' => false]);

        $zone = ServiceZone::factory()->create();
        $client = $this->makeClient();
        // Aucun employé éligible créé.
        $rdv = $this->makeBooking($zone, $client)->fresh(['client', 'serviceZone']);

        $best = app(AiDispatchService::class)->bestEmployeeFor($rdv);

        $this->assertNull($best);
    }

    public function test_best_employee_for_works_with_matching_enabled(): void
    {
        config(['matching.enabled' => true]);

        $zone = ServiceZone::factory()->create();
        $employee = $this->makeEmployee($zone->id);
        $client = $this->makeClient();
        $rdv = $this->makeBooking($zone, $client)->fresh(['client', 'serviceZone']);

        // Whatever path (v2, scorer fallback or v1) is taken, it must yield
        // the single eligible employee.
        $best = app(AiDispatchService::class)->bestEmployeeFor($rdv);

        $this->assertNotNull($best);
        $this->assertSame($employee->id, $best->id);
    }
}

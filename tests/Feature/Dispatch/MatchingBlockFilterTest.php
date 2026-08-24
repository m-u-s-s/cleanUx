<?php

namespace Tests\Feature\Dispatch;

use App\Enums\ProviderType;
use App\Models\Booking;
use App\Models\ProviderProfile;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\Dispatch\AiDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Audit HIGH — le matching ne doit jamais proposer un prestataire bloqué (dans un sens ou l'autre) vis-à-vis du client de la réservation. */
class MatchingBlockFilterTest extends TestCase
{
    use RefreshDatabase;

    private ServiceZone $zone;

    private User $client;

    private Trade $trade;

    protected function setUp(): void
    {
        parent::setUp();
        $this->zone = ServiceZone::factory()->create();
        $this->client = User::factory()->client()->create();

        // LE MÉTIER EST DÉSORMAIS OBLIGATOIRE.
        $this->trade = Trade::create([
            'slug' => 'nettoyage-block-test',
            'code' => 'CLEAN-BT',
            'name' => 'Nettoyage',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function eligibleEmployee(): User
    {
        $employee = User::factory()->employe()->create([
            'is_active' => true,
            'primary_service_zone_id' => $this->zone->id,
        ]);
        ProviderProfile::updateOrCreate(['user_id' => $employee->id], [
            'provider_type' => ProviderType::INDEPENDENT->value,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $employee->trades()->syncWithoutDetaching([$this->trade->id]);

        return $employee;
    }

    private function booking(): Booking
    {
        return Booking::factory()->create([
            'client_id' => $this->client->id,
            'service_zone_id' => $this->zone->id,
            'date' => now()->addDay()->toDateString(),
            'heure' => '10:00',
            'estimated_duration_minutes' => 90,
            'status' => 'en_attente',
            'trade_id' => $this->trade->id,
        ])->fresh(['client', 'serviceZone']);
    }

    private function rankedIds(Booking $booking): array
    {
        return app(AiDispatchService::class)->rankEmployees($booking)
            ->pluck('employee.id')->all();
    }

    public function test_baseline_eligible_employee_is_ranked(): void
    {
        $employee = $this->eligibleEmployee();

        $this->assertContains($employee->id, $this->rankedIds($this->booking()));
    }

    public function test_provider_blocked_by_client_is_excluded(): void
    {
        $employee = $this->eligibleEmployee();
        UserBlock::create(['blocker_user_id' => $this->client->id, 'blocked_user_id' => $employee->id]);

        $this->assertNotContains($employee->id, $this->rankedIds($this->booking()));
    }

    public function test_provider_who_blocked_the_client_is_excluded(): void
    {
        $employee = $this->eligibleEmployee();
        UserBlock::create(['blocker_user_id' => $employee->id, 'blocked_user_id' => $this->client->id]);

        $this->assertNotContains($employee->id, $this->rankedIds($this->booking()));
    }
}

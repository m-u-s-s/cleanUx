<?php

namespace Tests\Feature;

use App\Models\RendezVous;
use App\Models\ServiceZone;
use App\Models\User;
use App\Services\Dispatch\AiDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_dispatch_returns_empty_when_rdv_has_no_zone(): void
    {
        $rdv = RendezVous::factory()->create([
            'service_zone_id' => null,
            'date' => now()->addDay()->toDateString(),
            'heure' => '10:00',
        ]);

        $ranking = app(AiDispatchService::class)->rankEmployees($rdv);

        $this->assertCount(0, $ranking);
    }

    public function test_ai_dispatch_can_rank_available_employee(): void
    {
        $zone = ServiceZone::factory()->create();

        $employee = User::factory()->create([
            'role' => 'employe',
            'is_active' => true,
            'primary_service_zone_id' => $zone->id,
        ]);

        $client = User::factory()->create([
            'role' => 'client',
            'is_active' => true,
        ]);

        $rdv = RendezVous::factory()->create([
            'client_id' => $client->id,
            'service_zone_id' => $zone->id,
            'date' => now()->addDay()->toDateString(),
            'heure' => '10:00',
            'duree_estimee' => 90,
            'status' => 'en_attente',
        ]);

        $ranking = app(AiDispatchService::class)->rankEmployees(
            $rdv->fresh(['client', 'serviceZone'])
        );

        $this->assertNotEmpty($ranking);
        $this->assertEquals($employee->id, $ranking->first()['employee']->id);
    }
}
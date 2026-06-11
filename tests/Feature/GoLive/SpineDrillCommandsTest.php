<?php

namespace Tests\Feature\GoLive;

use App\Models\Mission;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit HIGH (go-live drills) — les commandes référencées par le runbook D1/D5
 * doivent exister et fonctionner pour que l'opérateur puisse exécuter les drills.
 */
class SpineDrillCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_stuck_missions_flags_a_stuck_mission(): void
    {
        Mission::factory()->create([
            'status' => MissionStatus::STARTED,
            'planned_start_at' => now()->subHours(10),
            'actual_end_at' => null,
        ]);

        $this->artisan('spine:check-stuck-missions', ['--hours' => 6])
            ->assertExitCode(1);
    }

    public function test_check_stuck_missions_passes_when_none(): void
    {
        Mission::factory()->create([
            'status' => MissionStatus::COMPLETED,
            'planned_start_at' => now()->subHours(10),
            'actual_end_at' => now()->subHours(8),
        ]);
        Mission::factory()->create([
            'status' => MissionStatus::STARTED,
            'planned_start_at' => now()->subHour(),  // récent → pas stuck
            'actual_end_at' => null,
        ]);

        $this->artisan('spine:check-stuck-missions', ['--hours' => 6])
            ->assertExitCode(0);
    }

    public function test_health_report_runs(): void
    {
        $this->artisan('spine:health-report')->assertExitCode(0);
    }
}

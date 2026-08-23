<?php

namespace Tests\Feature;

use App\Support\Platform\PlatformReadinessReport;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_builds_a_coherent_demo_state(): void
    {
        $this->seed(DatabaseSeeder::class);

        /** @var PlatformReadinessReport $readinessReport */
        $readinessReport = app(PlatformReadinessReport::class);

        $report = $readinessReport->build();

        $this->assertGreaterThanOrEqual(1, $report['metrics']['admins_total']);
        $this->assertGreaterThanOrEqual(1, $report['metrics']['employees_total']);
        $this->assertGreaterThanOrEqual(1, $report['metrics']['clients_total']);
        $this->assertGreaterThanOrEqual(1, $report['metrics']['organization_accounts_total']);
        $this->assertGreaterThanOrEqual(1, $report['metrics']['organization_sites_total']);
        $this->assertGreaterThanOrEqual(1, $report['metrics']['service_zones_total']);
        $this->assertGreaterThanOrEqual(1, $report['metrics']['zone_rules_total']);
        $this->assertGreaterThanOrEqual(1, $report['metrics']['rendezvous_total']);
        $this->assertGreaterThanOrEqual(1, $report['metrics']['feedbacks_total']);

        // LES ESPACES SOCIÉTÉ (2026-08-06).
        $this->assertGreaterThanOrEqual(1, $report['metrics']['field_teams_total']);
        $this->assertGreaterThanOrEqual(1, $report['metrics']['tasks_total']);
        $this->assertGreaterThanOrEqual(1, $report['metrics']['channels_total']);
        $this->assertGreaterThanOrEqual(1, $report['metrics']['missions_total']);

        $this->assertTrue($report['summary']['seed_ready'], 'Le report readiness signale encore des erreurs bloquantes.');
    }
}

<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\Platform\PlatformReadinessReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformReadinessReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_detects_blocking_issues_when_context_is_incomplete(): void
    {
        User::factory()->entreprise()->create([
            'organization_account_id' => null,
            'email' => 'entreprise-sans-compte@test.local',
        ]);

        User::factory()->employe()->create([
            'email' => 'employe-sans-zone@test.local',
        ]);

        /** @var PlatformReadinessReport $readinessReport */
        $readinessReport = app(PlatformReadinessReport::class);

        $report = $readinessReport->build();

        $checks = collect($report['checks'])->keyBy('key');

        $this->assertFalse($report['summary']['seed_ready']);
        $this->assertGreaterThan(0, $checks['entreprise_users_without_account']['count']);
        $this->assertGreaterThan(0, $checks['employees_without_active_zone_assignment']['count']);
    }
}

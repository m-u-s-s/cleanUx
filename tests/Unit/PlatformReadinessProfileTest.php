<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\Platform\PlatformReadinessReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformReadinessProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_flags_demo_artifacts_when_non_demo_profile_is_selected(): void
    {
        config()->set('cleanux.seed.profile', 'production');

        User::factory()->admin()->create([
            'email' => 'admin@cleanux.test',
        ]);

        /** @var PlatformReadinessReport $readinessReport */
        $readinessReport = app(PlatformReadinessReport::class);

        $report = $readinessReport->build();
        $checks = collect($report['checks'])->keyBy('key');

        $this->assertSame('production', $report['profile']);
        $this->assertGreaterThan(0, $checks['demo_artifacts_in_non_demo_profile']['count']);
        $this->assertSame(0, $checks['missing_demo_admin']['count']);
    }
}

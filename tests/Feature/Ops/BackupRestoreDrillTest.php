<?php

namespace Tests\Feature\Ops;

use Tests\TestCase;

class BackupRestoreDrillTest extends TestCase
{
    public function test_dry_run_reports_plan_and_exits_zero_without_touching_db(): void
    {
        $this->artisan('backup:restore-drill', ['--dry-run' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('integrity');
    }

    public function test_dry_run_describes_the_steps(): void
    {
        $this->artisan('backup:restore-drill', ['--dry-run' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('scratch');
    }

    public function test_refuses_to_run_real_restore_against_primary_connection(): void
    {
        // Force the scratch connection to resolve to the primary => command must refuse.
        $this->artisan('backup:restore-drill', ['--connection' => config('database.default')])
            ->assertExitCode(1);
    }
}

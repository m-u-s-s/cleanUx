<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class GoLiveReadinessReportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_succeeds_when_health_and_schedule_are_ready(): void
    {
        Config::set('app.env', 'production');
        Config::set('app.debug', false);
        Config::set('app.key', 'base64:test-key');
        Config::set('app.url', 'https://cleanux.test');
        Config::set('queue.default', 'database');
        Config::set('cache.default', 'file');
        Config::set('session.driver', 'database');
        Config::set('mail.default', 'smtp');
        Config::set('operations.backups.enabled', false);

        $this->artisan('app:go-live-readiness-report --strict')
            ->expectsOutput('Go-live readiness report OK.')
            ->assertExitCode(0);
    }
}

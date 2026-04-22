<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ProductionHealthCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_succeeds_with_good_production_like_config(): void
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

        $this->artisan('app:production-health-check --strict')
            ->expectsOutput('Production health check OK.')
            ->assertExitCode(0);
    }
}

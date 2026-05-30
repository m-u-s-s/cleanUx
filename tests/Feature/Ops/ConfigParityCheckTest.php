<?php

namespace Tests\Feature\Ops;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ConfigParityCheckTest extends TestCase
{
    public function test_passes_for_a_production_profile(): void
    {
        Config::set('database.default', 'mysql');
        Config::set('queue.default', 'redis');
        Config::set('cache.default', 'redis');
        Config::set('broadcasting.default', 'reverb');
        Config::set('session.driver', 'database');

        $this->artisan('config:parity-check')->assertExitCode(0);
    }

    public function test_fails_when_queue_is_sync(): void
    {
        Config::set('database.default', 'mysql');
        Config::set('queue.default', 'sync'); // prod-unsafe
        Config::set('cache.default', 'redis');
        Config::set('broadcasting.default', 'reverb');
        Config::set('session.driver', 'database');

        $this->artisan('config:parity-check')->assertExitCode(1);
    }

    public function test_fails_when_cache_is_file(): void
    {
        Config::set('database.default', 'mysql');
        Config::set('queue.default', 'redis');
        Config::set('cache.default', 'file'); // prod-unsafe
        Config::set('broadcasting.default', 'reverb');
        Config::set('session.driver', 'database');

        $this->artisan('config:parity-check')->assertExitCode(1);
    }

    public function test_names_the_offending_setting(): void
    {
        Config::set('database.default', 'mysql');
        Config::set('queue.default', 'sync');
        Config::set('cache.default', 'redis');
        Config::set('broadcasting.default', 'reverb');
        Config::set('session.driver', 'database');

        $this->artisan('config:parity-check')->expectsOutputToContain('queue')->assertExitCode(1);
    }
}

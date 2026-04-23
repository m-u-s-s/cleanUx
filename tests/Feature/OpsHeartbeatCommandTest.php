<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OpsHeartbeatCommandTest extends TestCase
{
    public function test_heartbeat_writes_file_and_cache(): void
    {
        Storage::fake('local');
        Config::set('operations.monitoring.heartbeat_enabled', true);
        Config::set('operations.monitoring.heartbeat_disk', 'local');
        Config::set('operations.monitoring.heartbeat_path', 'ops/heartbeat.json');
        Config::set('operations.monitoring.heartbeat_cache_key', 'cleanux:test:heartbeat');

        $this->artisan('app:ops-heartbeat')->assertExitCode(0);

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('local');

        $disk->assertExists('ops/heartbeat.json');
        $this->assertNotNull(Cache::get('cleanux:test:heartbeat'));
    }
}

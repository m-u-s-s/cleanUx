<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedPlatformCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_platform_command_rejects_unknown_profile(): void
    {
        $this->artisan('app:seed-platform sandbox')
            ->expectsOutputToContain('Profil invalide')
            ->assertExitCode(1);
    }
}

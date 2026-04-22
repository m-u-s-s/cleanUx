<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrepareFreshSeedCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_prepare_fresh_seed_command_passes_on_seeded_database(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->artisan('app:prepare-fresh-seed --strict')
            ->expectsOutputToContain('Checklist post-seed CleanUx')
            ->assertExitCode(0);
    }
}

<?php

namespace Tests\Feature;

use App\Models\ServiceCatalog;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_can_run_reference_profile_without_demo_data(): void
    {
        config()->set('cleanux.seed.profile', 'reference');

        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(0, ServiceCatalog::query()->count());
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('rendez_vous', 0);
        $this->assertDatabaseCount('feedback', 0);
    }

    public function test_database_seeder_can_run_demo_profile_with_demo_data(): void
    {
        config()->set('cleanux.seed.profile', 'demo');

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'admin@cleanux.test']);
        $this->assertDatabaseCount('rendez_vous', 4);
    }
}

<?php

namespace Tests\Feature;

use Database\Seeders\ProductionBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionBootstrapSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_bootstrap_loads_reference_data_without_demo_accounts(): void
    {
        $this->seed(ProductionBootstrapSeeder::class);

        $this->assertDatabaseCount('service_catalogs', 6);
        $this->assertDatabaseMissing('users', ['email' => 'admin@cleanux.test']);
        $this->assertDatabaseCount('rendez_vous', 0);
        $this->assertDatabaseCount('feedback', 0);
    }
}

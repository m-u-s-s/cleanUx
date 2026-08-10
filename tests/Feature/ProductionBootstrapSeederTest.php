<?php

namespace Tests\Feature;

use App\Models\ServiceCatalog;
use Database\Seeders\ProductionBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionBootstrapSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_bootstrap_loads_reference_data_without_demo_accounts(): void
    {
        $this->seed(ProductionBootstrapSeeder::class);

        // ServiceCatalogSeeder now seeds ~45 canonical services across all 12 trades.
        $this->assertGreaterThanOrEqual(6, ServiceCatalog::count());
        $this->assertDatabaseMissing('users', ['email' => 'admin@brio.test']);
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('feedback', 0);
    }
}

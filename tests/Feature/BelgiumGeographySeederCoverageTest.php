<?php

namespace Tests\Feature;

use Database\Seeders\BelgiumGeographySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BelgiumGeographySeederCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_belgium_reference_contains_extended_postal_code_coverage_and_aliases(): void
    {
        $this->seed(BelgiumGeographySeeder::class);

        $this->assertDatabaseCount('regions', 3);
        $this->assertDatabaseCount('provinces', 11);
        $this->assertDatabaseHas('postal_codes', ['code' => '1000', 'city_name' => 'Bruxelles']);
        $this->assertDatabaseHas('postal_codes', ['code' => '1000', 'city_name' => 'Brussel']);
        $this->assertDatabaseHas('postal_codes', ['code' => '9000', 'city_name' => 'Gent']);
        $this->assertDatabaseHas('postal_codes', ['code' => '4000', 'city_name' => 'Luik']);
        $this->assertDatabaseHas('postal_codes', ['code' => '1400', 'city_name' => 'Nijvel']);

        $this->assertGreaterThanOrEqual(60, \App\Models\PostalCode::query()->count());
        $this->assertGreaterThanOrEqual(30, \App\Models\Commune::query()->count());
    }
}

<?php

namespace Tests\Feature\Search;

use App\Models\PostalCode;
use App\Services\Search\AddressAutocompleteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressAutocompleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_numeric_query_searches_by_code(): void
    {
        PostalCode::factory()->create(['code' => '1000', 'city_name' => 'Bruxelles', 'is_active' => true]);
        PostalCode::factory()->create(['code' => '1050', 'city_name' => 'Ixelles', 'is_active' => true]);
        PostalCode::factory()->create(['code' => '2000', 'city_name' => 'Anvers', 'is_active' => true]);

        $results = app(AddressAutocompleteService::class)->search('10', null, 10);

        $codes = $results->pluck('code')->all();
        $this->assertContains('1000', $codes);
        $this->assertContains('1050', $codes);
        $this->assertNotContains('2000', $codes);
    }

    public function test_text_query_searches_by_city(): void
    {
        PostalCode::factory()->create(['code' => '1000', 'city_name' => 'Bruxelles', 'is_active' => true]);
        PostalCode::factory()->create(['code' => '1050', 'city_name' => 'Bruges', 'is_active' => true]);
        PostalCode::factory()->create(['code' => '2000', 'city_name' => 'Anvers', 'is_active' => true]);

        $results = app(AddressAutocompleteService::class)->search('bru', null, 10);

        $cities = $results->pluck('city_name')->all();
        $this->assertContains('Bruxelles', $cities);
        $this->assertContains('Bruges', $cities);
        $this->assertNotContains('Anvers', $cities);
    }

    public function test_short_query_returns_empty(): void
    {
        PostalCode::factory()->create(['code' => '1', 'city_name' => 'X', 'is_active' => true]);
        $results = app(AddressAutocompleteService::class)->search('a', null, 10);
        $this->assertCount(0, $results);
    }

    public function test_inactive_postal_codes_excluded(): void
    {
        PostalCode::factory()->create(['code' => '1000', 'city_name' => 'Active', 'is_active' => true]);
        PostalCode::factory()->create(['code' => '1001', 'city_name' => 'Inactive', 'is_active' => false]);

        $results = app(AddressAutocompleteService::class)->search('10', null, 10);

        $cities = $results->pluck('city_name')->all();
        $this->assertContains('Active', $cities);
        $this->assertNotContains('Inactive', $cities);
    }

    public function test_results_include_lat_lng_when_present(): void
    {
        PostalCode::factory()->create([
            'code' => '1000',
            'city_name' => 'Bruxelles',
            'is_active' => true,
            'lat' => 50.85,
            'lng' => 4.35,
        ]);

        $results = app(AddressAutocompleteService::class)->search('1000', null, 1);

        $this->assertCount(1, $results);
        $this->assertEqualsWithDelta(50.85, $results->first()['lat'], 0.01);
        $this->assertEqualsWithDelta(4.35, $results->first()['lng'], 0.01);
    }
}

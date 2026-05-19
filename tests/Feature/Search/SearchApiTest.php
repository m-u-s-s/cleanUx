<?php

namespace Tests\Feature\Search;

use App\Models\PostalCode;
use App\Models\ProviderProfile;
use App\Models\ServiceCatalog;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_providers_search_returns_paginated_payload(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->create(['role' => 'employe']);
            ProviderProfile::create([
                'user_id' => $user->id,
                'provider_type' => 'independent',
                'status' => 'active',
                'verification_status' => 'verified',
                'rating_avg' => 4.5,
                'rating_count' => 10,
                'hourly_rate' => 40 + $i * 10,
            ]);
        }

        $response = $this->getJson('/api/search/providers');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'rating', 'hourly_rate', 'is_online', 'profile_url'],
            ],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ]);
        $this->assertSame(3, $response->json('meta.total'));
    }

    public function test_providers_search_min_rating_filter_via_api(): void
    {
        $low = User::factory()->create(['role' => 'employe']);
        ProviderProfile::create([
            'user_id' => $low->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'verified',
            'rating_avg' => 2.5,
            'rating_count' => 5,
        ]);

        $high = User::factory()->create(['role' => 'employe']);
        ProviderProfile::create([
            'user_id' => $high->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'verified',
            'rating_avg' => 4.9,
            'rating_count' => 30,
        ]);

        $response = $this->getJson('/api/search/providers?min_rating=4');
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($high->id, $ids);
        $this->assertNotContains($low->id, $ids);
    }

    public function test_services_search_returns_active_only(): void
    {
        $trade = Trade::create([
            'name' => 'Search test trade',
            'slug' => 'search-test-' . uniqid(),
            'code' => 'STT' . substr(uniqid(), -6),
            'is_active' => true,
        ]);
        ServiceCatalog::factory()->create(['trade_id' => $trade->id, 'is_active' => true, 'name' => 'Active service']);
        ServiceCatalog::factory()->create(['trade_id' => $trade->id, 'is_active' => false, 'name' => 'Inactive service']);

        $response = $this->getJson('/api/search/services');
        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Active service', $names);
        $this->assertNotContains('Inactive service', $names);
    }

    public function test_postal_autocomplete_returns_matches(): void
    {
        PostalCode::factory()->create(['code' => '1000', 'city_name' => 'Bruxelles', 'is_active' => true]);
        PostalCode::factory()->create(['code' => '1050', 'city_name' => 'Ixelles', 'is_active' => true]);

        $response = $this->getJson('/api/search/postal-autocomplete?q=10');
        $response->assertOk();
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    public function test_postal_autocomplete_validates_min_length(): void
    {
        $response = $this->getJson('/api/search/postal-autocomplete?q=a');
        $response->assertStatus(422);
    }
}

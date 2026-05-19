<?php

namespace Tests\Feature\Search;

use App\Models\PostalCode;
use App\Models\ProviderProfile;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use App\Services\Search\ProviderSearchCriteria;
use App\Services\Search\ProviderSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ProviderSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProviderSearchService::class);
    }

    public function test_only_active_verified_providers_are_returned(): void
    {
        $verified = $this->makeProvider(['verification_status' => 'verified', 'status' => 'active']);
        $unverified = $this->makeProvider(['verification_status' => 'unverified', 'status' => 'active']);
        $inactive = $this->makeProvider(['verification_status' => 'verified', 'status' => 'pending']);

        $results = $this->service->search(new ProviderSearchCriteria());

        $ids = collect($results->items())->pluck('id')->all();
        $this->assertContains($verified->id, $ids);
        $this->assertNotContains($unverified->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
    }

    public function test_min_rating_filter_excludes_lower_ratings(): void
    {
        $highRated = $this->makeProvider(['rating_avg' => 4.8, 'rating_count' => 20]);
        $lowRated = $this->makeProvider(['rating_avg' => 3.2, 'rating_count' => 5]);

        $results = $this->service->search(new ProviderSearchCriteria(minRating: 4.0));

        $ids = collect($results->items())->pluck('id')->all();
        $this->assertContains($highRated->id, $ids);
        $this->assertNotContains($lowRated->id, $ids);
    }

    public function test_price_range_filter(): void
    {
        $cheap = $this->makeProvider(['hourly_rate' => 25]);
        $mid = $this->makeProvider(['hourly_rate' => 50]);
        $expensive = $this->makeProvider(['hourly_rate' => 100]);

        $results = $this->service->search(new ProviderSearchCriteria(minPrice: 30, maxPrice: 70));

        $ids = collect($results->items())->pluck('id')->all();
        $this->assertNotContains($cheap->id, $ids);
        $this->assertContains($mid->id, $ids);
        $this->assertNotContains($expensive->id, $ids);
    }

    public function test_sort_by_rating_orders_descending(): void
    {
        $low = $this->makeProvider(['rating_avg' => 3.0, 'rating_count' => 10]);
        $high = $this->makeProvider(['rating_avg' => 4.9, 'rating_count' => 50]);
        $this->makeProvider(['rating_avg' => 4.0, 'rating_count' => 20]);

        $results = $this->service->search(new ProviderSearchCriteria(sort: 'rating'));

        $ids = collect($results->items())->pluck('id')->all();
        $this->assertSame($high->id, $ids[0]);
        $this->assertSame($low->id, $ids[count($ids) - 1]);
    }

    public function test_sort_by_price_asc(): void
    {
        $cheap = $this->makeProvider(['hourly_rate' => 20]);
        $this->makeProvider(['hourly_rate' => 80]);

        $results = $this->service->search(new ProviderSearchCriteria(sort: 'price_asc'));

        $ids = collect($results->items())->pluck('id')->all();
        $this->assertSame($cheap->id, $ids[0]);
    }

    public function test_trade_filter_only_includes_provider_with_trade(): void
    {
        $trade = $this->makeTrade();

        $withTrade = $this->makeProvider();
        $withTrade->trades()->attach($trade->id, ['is_primary' => true]);

        $withoutTrade = $this->makeProvider();

        $results = $this->service->search(new ProviderSearchCriteria(tradeId: $trade->id));

        $ids = collect($results->items())->pluck('id')->all();
        $this->assertContains($withTrade->id, $ids);
        $this->assertNotContains($withoutTrade->id, $ids);
    }

    public function test_postal_filter_includes_only_providers_in_matching_zone(): void
    {
        $zone = ServiceZone::factory()->create();
        PostalCode::factory()->create([
            'service_zone_id' => $zone->id,
            'code' => '1000',
            'is_active' => true,
        ]);

        $inZone = $this->makeProvider();
        $inZone->forceFill(['primary_service_zone_id' => $zone->id])->save();

        $outZone = $this->makeProvider();

        $results = $this->service->search(new ProviderSearchCriteria(postalCode: '1000'));

        $ids = collect($results->items())->pluck('id')->all();
        $this->assertContains($inZone->id, $ids);
        $this->assertNotContains($outZone->id, $ids);
    }

    public function test_unknown_postal_returns_empty(): void
    {
        $this->makeProvider();

        $results = $this->service->search(new ProviderSearchCriteria(postalCode: '99999'));

        $this->assertSame(0, $results->total());
    }

    protected function makeProvider(array $profileAttrs = []): User
    {
        $user = User::factory()->create(['role' => 'employe']);
        ProviderProfile::create(array_merge([
            'user_id' => $user->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'verified',
            'rating_avg' => 4.5,
            'rating_count' => 10,
        ], $profileAttrs));

        return $user->fresh();
    }

    protected function makeTrade(?string $name = null): Trade
    {
        return Trade::create([
            'name' => $name ?? 'Test Trade ' . uniqid(),
            'slug' => 'test-trade-' . uniqid(),
            'code' => strtoupper('TT' . substr(uniqid(), -6)),
            'is_active' => true,
        ]);
    }
}

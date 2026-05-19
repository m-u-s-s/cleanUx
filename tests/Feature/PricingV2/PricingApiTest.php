<?php

namespace Tests\Feature\PricingV2;

use App\Models\PriceQuote;
use App\Models\User;
use Database\Seeders\PricingV2Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PricingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingV2Seeder::class);
        Config::set('pricing_v2.enabled', true);
        Config::set('pricing_v2.quote_rate_limit_per_minute', 1000);
    }

    public function test_services_endpoint_returns_active_services(): void
    {
        $response = $this->getJson('/api/v2/pricing/services');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(2, count($data));
        $codes = collect($data)->pluck('code')->all();
        $this->assertContains('cleaning_standard', $codes);
    }

    public function test_services_endpoint_filters_by_trade(): void
    {
        $response = $this->getJson('/api/v2/pricing/services?trade_code=cleaning');

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('cleaning_standard', $data[0]['code']);
    }

    public function test_preview_endpoint_returns_computed_price(): void
    {
        $response = $this->postJson('/api/v2/pricing/preview', [
            'service_code' => 'cleaning_standard',
            'variables' => ['urgency' => 'urgent'],
        ]);

        $response->assertOk();
        $this->assertSame(6000, $response->json('preview.computed_price_cents'));
        $this->assertSame(0, PriceQuote::count());  // preview is non-persistent
    }

    public function test_quote_endpoint_persists_in_ledger(): void
    {
        $response = $this->postJson('/api/v2/pricing/quote', [
            'service_code' => 'cleaning_standard',
            'variables' => ['surface_m2' => 100],
        ]);

        $response->assertStatus(201);
        $this->assertSame(10000, $response->json('quote.computed_price_cents'));
        $this->assertSame(1, PriceQuote::count());
    }

    public function test_quote_endpoint_validates_required_service_code(): void
    {
        $this->postJson('/api/v2/pricing/quote', ['variables' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['service_code']);
    }

    public function test_quote_endpoint_returns_422_for_unknown_service(): void
    {
        $this->postJson('/api/v2/pricing/quote', [
            'service_code' => 'arbitrary',
        ])->assertStatus(422);
    }

    public function test_quote_endpoint_attaches_user_when_authenticated(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v2/pricing/quote', [
            'service_code' => 'cleaning_standard',
        ])->assertStatus(201);

        $this->assertSame($user->id, PriceQuote::first()->user_id);
    }

    public function test_admin_quotes_endpoint_returns_ledger(): void
    {
        $admin = User::factory()->admin()->create();

        $this->postJson('/api/v2/pricing/quote', ['service_code' => 'cleaning_standard']);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/pricing-v2/quotes');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}

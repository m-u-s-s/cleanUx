<?php

namespace Tests\Feature\Fx;

use App\Models\CurrencyConversion;
use App\Services\Fx\FxProviderInterface;
use App\Services\Fx\Providers\FxMockProvider;
use Database\Seeders\CurrenciesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FxApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(FxProviderInterface::class, FxMockProvider::class);
        $this->seed(CurrenciesSeeder::class);
        Cache::flush();
    }

    public function test_currencies_endpoint_returns_active_currencies(): void
    {
        $response = $this->getJson('/api/fx/currencies');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(10, count($data));
        $codes = collect($data)->pluck('code')->all();
        $this->assertContains('EUR', $codes);
        $this->assertContains('USD', $codes);
    }

    public function test_rates_endpoint_validates_quotes(): void
    {
        $this->getJson('/api/fx/rates')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quotes']);
    }

    public function test_rates_endpoint_returns_rates(): void
    {
        $response = $this->getJson('/api/fx/rates?base=EUR&quotes=USD,GBP');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
        // Un taux nul ou negatif casse toute conversion qui l'emploie : les nommer tous evite de
        // corriger la table ligne par ligne.
        $fautifs = [];

        foreach ($data as $r) {
            if (($r['base'] ?? null) !== 'EUR') {
                $fautifs[] = sprintf('%s : base « %s »', $r['quote'] ?? '?', $r['base'] ?? 'null');
            }

            if (($r['rate'] ?? 0) <= 0) {
                $fautifs[] = sprintf('%s : taux %s', $r['quote'] ?? '?', var_export($r['rate'] ?? null, true));
            }
        }

        $this->assertSame([], $fautifs, 'Ces taux de change sont inexploitables.');
    }

    public function test_convert_endpoint_validates_required_fields(): void
    {
        $this->postJson('/api/fx/convert', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount_cents', 'from', 'to']);
    }

    public function test_convert_endpoint_returns_target_amount(): void
    {
        $response = $this->postJson('/api/fx/convert', [
            'amount_cents' => 10000,
            'from' => 'EUR',
            'to' => 'USD',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'ok',
            'conversion' => [
                'source_amount_cents', 'source_currency',
                'target_amount_cents', 'target_currency',
                'rate_used', 'fee_percent', 'converted_at',
            ],
        ]);

        $this->assertSame(1, CurrencyConversion::count());
    }

    public function test_convert_endpoint_is_idempotent_with_key(): void
    {
        $payload = [
            'amount_cents' => 10000,
            'from' => 'EUR',
            'to' => 'USD',
            'idempotency_key' => 'api-idem-001',
        ];

        $this->postJson('/api/fx/convert', $payload)->assertOk();
        $this->postJson('/api/fx/convert', $payload)->assertOk();

        $this->assertSame(1, CurrencyConversion::count());
    }
}

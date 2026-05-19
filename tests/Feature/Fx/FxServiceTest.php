<?php

namespace Tests\Feature\Fx;

use App\Models\CurrencyConversion;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\Fx\FxProviderInterface;
use App\Services\Fx\FxService;
use App\Services\Fx\Providers\FxMockProvider;
use Database\Seeders\CurrenciesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class FxServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(FxProviderInterface::class, FxMockProvider::class);
        $this->seed(CurrenciesSeeder::class);
        Cache::flush();
        Config::set('fx.enabled', true);
        Config::set('fx.base_currency', 'EUR');
        Config::set('fx.cache_ttl_minutes', 15);
        Config::set('fx.fallback_chain', ['mock']);
        Config::set('fx.fee_percent', 0);
    }

    public function test_get_rate_returns_identity_for_same_currency(): void
    {
        $rate = app(FxService::class)->getRate('EUR', 'EUR');

        $this->assertNotNull($rate);
        $this->assertSame('1.00000000', (string) $rate->rate);
    }

    public function test_get_rate_fetches_from_mock_provider_and_persists(): void
    {
        $rate = app(FxService::class)->getRate('EUR', 'USD');

        $this->assertNotNull($rate);
        $this->assertSame('mock', $rate->source);
        $this->assertGreaterThan(0, (float) $rate->rate);
        $this->assertSame(1, ExchangeRate::query()->pair('EUR', 'USD')->count());
    }

    public function test_get_rate_reuses_fresh_db_row_within_ttl(): void
    {
        ExchangeRate::create([
            'base_currency' => 'EUR',
            'quote_currency' => 'GBP',
            'rate' => '0.85500000',
            'source' => 'mock',
            'fetched_at' => now()->subMinutes(5),
            'valid_from' => now()->subMinutes(5),
        ]);

        $rate = app(FxService::class)->getRate('EUR', 'GBP');

        $this->assertNotNull($rate);
        $this->assertSame('0.85500000', (string) $rate->rate);
        // No new row created
        $this->assertSame(1, ExchangeRate::query()->pair('EUR', 'GBP')->count());
    }

    public function test_get_rate_refetches_when_db_row_stale(): void
    {
        Config::set('fx.cache_ttl_minutes', 5);

        ExchangeRate::create([
            'base_currency' => 'EUR',
            'quote_currency' => 'USD',
            'rate' => '0.50000000',  // stale fake rate
            'source' => 'manual',
            'fetched_at' => now()->subHours(2),
            'valid_from' => now()->subHours(2),
        ]);

        Cache::flush();

        $rate = app(FxService::class)->getRate('EUR', 'USD');

        $this->assertNotNull($rate);
        $this->assertSame('mock', $rate->source);  // fresh fetch, not the stale one
    }

    public function test_get_rate_uses_fallback_when_quote_not_supported(): void
    {
        // FAIL trigger in mock provider → empty rates → falls through to ultimate fallback 1:1
        Config::set('fx.fallback_chain', []);

        $rate = app(FxService::class)->getRate('EUR', 'FAIL');

        $this->assertNotNull($rate);
        $this->assertSame(ExchangeRate::SOURCE_FALLBACK, $rate->source);
        $this->assertSame('1.00000000', (string) $rate->rate);
    }

    public function test_convert_creates_currency_conversion_record(): void
    {
        $user = User::factory()->client()->create();

        $conv = app(FxService::class)->convert(
            amountCents: 10000,  // 100 EUR
            sourceCurrency: 'EUR',
            targetCurrency: 'USD',
            user: $user,
        );

        $this->assertInstanceOf(CurrencyConversion::class, $conv);
        $this->assertSame(10000, (int) $conv->source_amount_cents);
        $this->assertSame('EUR', $conv->source_currency);
        $this->assertSame('USD', $conv->target_currency);
        $this->assertGreaterThan(0, (int) $conv->target_amount_cents);
        $this->assertSame($user->id, $conv->user_id);
    }

    public function test_convert_applies_fee_percent(): void
    {
        Config::set('fx.fee_percent', 2.0);  // 2% fee

        $conv = app(FxService::class)->convert(
            amountCents: 10000,
            sourceCurrency: 'EUR',
            targetCurrency: 'USD',
        );

        $rate = (float) $conv->rate_used;
        $expected = (int) round(10000 * $rate * 1.02);
        $this->assertSame($expected, (int) $conv->target_amount_cents);
        $this->assertSame('2.0000', (string) $conv->fee_percent);
    }

    public function test_convert_is_idempotent(): void
    {
        $svc = app(FxService::class);

        $a = $svc->convert(10000, 'EUR', 'USD', idempotencyKey: 'idem-001');
        $b = $svc->convert(10000, 'EUR', 'USD', idempotencyKey: 'idem-001');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, CurrencyConversion::count());
    }

    public function test_convert_same_currency_is_identity(): void
    {
        $conv = app(FxService::class)->convert(10000, 'EUR', 'EUR');

        $this->assertSame(10000, (int) $conv->target_amount_cents);
        $this->assertSame('1.00000000', (string) $conv->rate_used);
    }

    public function test_refresh_all_creates_rates_for_each_active_currency(): void
    {
        $count = app(FxService::class)->refreshAll('EUR');

        // 11 currencies other than EUR seeded (USD, GBP, CHF, CAD, AUD, JPY, NOK, SEK, DKK, PLN, CZK)
        $this->assertGreaterThanOrEqual(11, ExchangeRate::query()->where('base_currency', 'EUR')->count());
    }

    public function test_get_rate_caches_result(): void
    {
        $svc = app(FxService::class);
        $a = $svc->getRate('EUR', 'USD');

        // Tamper DB - cache should still return original
        ExchangeRate::query()->where('id', $a->id)->update(['rate' => '999.99999999']);

        $b = $svc->getRate('EUR', 'USD');
        $this->assertSame((float) $a->rate, (float) $b->rate);
    }
}

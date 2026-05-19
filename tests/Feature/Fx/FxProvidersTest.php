<?php

namespace Tests\Feature\Fx;

use App\Services\Fx\Providers\FxMockProvider;
use Tests\TestCase;

class FxProvidersTest extends TestCase
{
    public function test_mock_provider_returns_eur_to_usd_rate(): void
    {
        $rates = (new FxMockProvider())->fetchRates('EUR', ['USD', 'GBP']);

        $this->assertCount(2, $rates);
        $codes = array_map(fn ($r) => $r->quote, $rates);
        $this->assertContains('USD', $codes);
        $this->assertContains('GBP', $codes);

        foreach ($rates as $r) {
            $this->assertGreaterThan(0, $r->rate);
            $this->assertSame('mock', $r->source);
        }
    }

    public function test_mock_provider_cross_rate_via_eur(): void
    {
        // USD → GBP : not direct, but mock supports cross-rate via EUR
        $rates = (new FxMockProvider())->fetchRates('USD', ['GBP']);

        $this->assertCount(1, $rates);
        $this->assertSame('USD', $rates[0]->base);
        $this->assertSame('GBP', $rates[0]->quote);
        $this->assertGreaterThan(0, $rates[0]->rate);
    }

    public function test_mock_provider_returns_empty_on_fail_trigger(): void
    {
        $rates = (new FxMockProvider())->fetchRates('EUR', ['FAIL']);

        $this->assertSame([], $rates);
    }

    public function test_mock_provider_returns_identity_for_same_quote(): void
    {
        $rates = (new FxMockProvider())->fetchRates('EUR', ['EUR']);

        $this->assertCount(1, $rates);
        $this->assertSame(1.0, $rates[0]->rate);
    }

    public function test_mock_provider_returns_inverted_for_eur_quote_from_other_base(): void
    {
        $rates = (new FxMockProvider())->fetchRates('USD', ['EUR']);

        $this->assertCount(1, $rates);
        $this->assertGreaterThan(0, $rates[0]->rate);
        $this->assertLessThan(1.5, $rates[0]->rate);  // EUR per USD ~0.92
    }
}

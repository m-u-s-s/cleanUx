<?php

namespace Tests\Unit;

use App\Models\Country;
use App\Models\CountryBillingProfile;
use App\Models\CountryOperationalSetting;
use App\Models\CountryServiceCatalogRule;
use App\Models\PostalCode;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Services\International\CountryMarketResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CountryMarketResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_market_context_for_booking(): void
    {
        $country = Country::factory()->create(['iso_code' => 'US', 'currency_code' => 'USD', 'name' => 'United States']);

        CountryOperationalSetting::query()->create([
            'country_id' => $country->id,
            'booking_enabled' => true,
            'billing_enabled' => true,
            'market_stage' => 'ready_for_launch',
            'currency_symbol' => '$',
            'date_format' => 'm/d/Y',
            'time_format' => 'h:i A',
            'default_tax_rate' => 8.25,
        ]);

        CountryBillingProfile::query()->create([
            'country_id' => $country->id,
            'currency_code' => 'USD',
            'currency_symbol' => '$',
            'quote_prefix' => 'Q-US',
            'invoice_prefix' => 'I-US',
            'tax_label' => 'Sales tax',
            'default_tax_rate' => 8.25,
            'payment_terms_days' => 21,
            'quote_validity_days' => 10,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'currency_position' => 'before',
        ]);

        $catalog = ServiceCatalog::factory()->create();
        CountryServiceCatalogRule::query()->create([
            'country_id' => $country->id,
            'service_catalog_id' => $catalog->id,
            'is_enabled' => true,
            'requires_quote' => true,
            'requires_manual_validation' => true,
            'minimum_notice_hours' => 12,
            'price_multiplier' => 1.15,
        ]);

        $zone = ServiceZone::factory()->create(['country_id' => $country->id]);
        $postal = PostalCode::factory()->create(['country_id' => $country->id]);

        $context = app(CountryMarketResolver::class)->resolveForBooking(null, $postal, $zone, null, $catalog);

        $this->assertSame('US', $context['country']->iso_code);
        $this->assertTrue(app(CountryMarketResolver::class)->bookingEnabled($context));
        $this->assertTrue(app(CountryMarketResolver::class)->requiresQuote($context));
        $this->assertSame(12, app(CountryMarketResolver::class)->minimumNoticeHours($context));
        $this->assertSame('USD', app(CountryMarketResolver::class)->effectiveCurrency($context));
        $this->assertSame('$', app(CountryMarketResolver::class)->formatting($context)['currency_symbol']);
    }
}

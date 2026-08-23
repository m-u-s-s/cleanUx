<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Services\Dispatch\MatchingScorer;
use App\Services\Payments\CommissionService;
use App\Services\Payments\StripeCountryMapper;
use App\Services\Tax\TaxCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** BusinessLogicTest — validates monetisation + metier depth core logic. */
class BusinessLogicTest extends TestCase
{
    use RefreshDatabase;

    // ─── CommissionService ────────────────────────────────────────────────────

    public function test_commission_calculates_15_percent_default(): void
    {
        // Scénario explicite à 15% (indépendant du taux d'env, fixé à 20% pour
        // matcher la prod dans le reste de la suite money).
        config(['brio.platform_fee_percent' => 15]);

        $service = new CommissionService;
        $booking = Booking::factory()->create(['devis_estime' => 100]);

        $result = $service->calculateForBooking($booking);

        $this->assertEquals(10000, $result['total_cents']);
        $this->assertEquals(1500, $result['platform_fee_cents']);
        $this->assertEquals(8500, $result['provider_payout_cents']);
        $this->assertEquals(0.15, $result['commission_rate']);
        $this->assertEquals('eur', $result['currency']);
    }

    public function test_commission_respects_minimum(): void
    {
        $service = new CommissionService;
        // €5 booking × 15% = €0.75 → below €2 minimum → clamp to €2
        $booking = Booking::factory()->create(['devis_estime' => 5]);

        $result = $service->calculateForBooking($booking);

        $this->assertEquals(200, $result['platform_fee_cents']); // €2.00 minimum
        $this->assertEquals(300, $result['provider_payout_cents']);
    }

    public function test_commission_fee_never_exceeds_total(): void
    {
        $service = new CommissionService;
        // €1 booking — minimum fee would exceed total, must be capped
        $booking = Booking::factory()->create(['devis_estime' => 1]);

        $result = $service->calculateForBooking($booking);

        $this->assertLessThanOrEqual($result['total_cents'], $result['platform_fee_cents']);
        $this->assertGreaterThanOrEqual(0, $result['provider_payout_cents']);
    }

    public function test_commission_zero_booking_value(): void
    {
        $service = new CommissionService;
        $booking = Booking::factory()->create(['devis_estime' => 0]);

        $result = $service->calculateForBooking($booking);

        $this->assertEquals(0, $result['total_cents']);
        $this->assertEquals(0, $result['provider_payout_cents']);
    }

    // ─── TaxCalculator ────────────────────────────────────────────────────────

    public function test_vat_calculator_belgium(): void
    {
        $calc = new TaxCalculator;
        $result = $calc->calculateVat(100, 'BE');

        $this->assertEquals(0.21, $result['vat_rate']);
        $this->assertEquals(21.0, $result['vat_amount']);
        $this->assertEquals(121.0, $result['amount_incl_vat']);
        $this->assertEquals('BE', $result['country_code']);
    }

    public function test_vat_calculator_france(): void
    {
        $calc = new TaxCalculator;
        $result = $calc->calculateVat(100, 'FR');

        $this->assertEquals(0.20, $result['vat_rate']);
        $this->assertEquals(20.0, $result['vat_amount']);
        $this->assertEquals(120.0, $result['amount_incl_vat']);
    }

    public function test_vat_calculator_germany(): void
    {
        $calc = new TaxCalculator;
        $result = $calc->calculateVat(100, 'DE');

        $this->assertEquals(0.19, $result['vat_rate']);
        $this->assertEquals(19.0, $result['vat_amount']);
    }

    public function test_vat_calculator_unknown_country_uses_fallback(): void
    {
        $calc = new TaxCalculator;
        $result = $calc->calculateVat(100, 'XX');

        $this->assertEquals(0.21, $result['vat_rate']); // fallback = BE
    }

    public function test_vat_extractor_roundtrips(): void
    {
        $calc = new TaxCalculator;

        $calculated = $calc->calculateVat(100, 'BE');
        $extracted = $calc->extractVat($calculated['amount_incl_vat'], 'BE');

        $this->assertEquals(100.0, $extracted['amount_excl_vat']);
    }

    public function test_vat_supported_countries_list(): void
    {
        $calc = new TaxCalculator;

        $this->assertContains('BE', $calc->supportedCountries());
        $this->assertContains('FR', $calc->supportedCountries());
        $this->assertContains('DE', $calc->supportedCountries());
    }

    // ─── MatchingScorer ───────────────────────────────────────────────────────

    public function test_matching_scorer_returns_sorted_providers(): void
    {
        $scorer = new MatchingScorer;
        $booking = Booking::factory()->create();
        $providers = User::factory()->count(3)->create(['role' => 'employe']);

        $results = $scorer->scoreProviders($booking, $providers);

        $this->assertCount(3, $results);
        $this->assertArrayHasKey('total_score', $results->first());
        $this->assertArrayHasKey('provider_id', $results->first());
        $this->assertArrayHasKey('scores', $results->first());

        // Verify sorted descending
        $scores = $results->pluck('total_score')->all();
        $this->assertEquals($scores, collect($scores)->sortDesc()->values()->all());
    }

    public function test_matching_scorer_scores_have_expected_keys(): void
    {
        $scorer = new MatchingScorer;
        $booking = Booking::factory()->create();
        $user = User::factory()->create(['role' => 'employe']);

        $results = $scorer->scoreProviders($booking, collect([$user]));
        $scores = $results->first()['scores'];

        $this->assertArrayHasKey('distance', $scores);
        $this->assertArrayHasKey('rating', $scores);
        $this->assertArrayHasKey('availability', $scores);
        $this->assertArrayHasKey('completion_rate', $scores);
        $this->assertArrayHasKey('price', $scores);
    }

    public function test_matching_scorer_empty_collection(): void
    {
        $scorer = new MatchingScorer;
        $booking = Booking::factory()->create();

        $results = $scorer->scoreProviders($booking, collect([]));

        $this->assertCount(0, $results);
    }

    // ─── StripeCountryMapper ──────────────────────────────────────────────────

    public function test_stripe_country_mapper_nl_has_ideal(): void
    {
        $mapper = new StripeCountryMapper;

        $this->assertContains('ideal_payments', $mapper->getConfig('NL')['capabilities']);
    }

    public function test_stripe_country_mapper_fr_no_ideal(): void
    {
        $mapper = new StripeCountryMapper;

        $this->assertNotContains('ideal_payments', $mapper->getConfig('FR')['capabilities']);
    }

    public function test_stripe_country_mapper_de_has_sepa(): void
    {
        $mapper = new StripeCountryMapper;

        $this->assertContains('sepa_debit_payments', $mapper->getConfig('DE')['capabilities']);
    }

    public function test_stripe_country_mapper_unsupported_falls_back_to_be(): void
    {
        $mapper = new StripeCountryMapper;

        $config = $mapper->getConfig('ZZ');
        $this->assertEquals('eur', $config['currency']);
        $this->assertContains('card_payments', $config['capabilities']);
    }

    public function test_stripe_country_mapper_capabilities_format(): void
    {
        $mapper = new StripeCountryMapper;
        $caps = $mapper->getCapabilities('BE');

        $this->assertArrayHasKey('card_payments', $caps);
        $this->assertEquals(['requested' => true], $caps['card_payments']);
    }

    public function test_stripe_country_mapper_supports_capability(): void
    {
        $mapper = new StripeCountryMapper;

        $this->assertTrue($mapper->supportsCapability('NL', 'ideal_payments'));
        $this->assertFalse($mapper->supportsCapability('FR', 'ideal_payments'));
    }
}

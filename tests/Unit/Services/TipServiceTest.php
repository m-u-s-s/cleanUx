<?php

namespace Tests\Unit\Services;

use App\Models\Booking;
use App\Services\Tips\TipService;
use Tests\TestCase;

/**
 * Unit tests for TipService::suggestionsForBooking().
 *
 * These tests focus on the pure calculation logic and do not require a
 * database, because suggestionsForBooking() works only with in-memory
 * Booking attributes and config values.
 */
class TipServiceTest extends TestCase
{
    private TipService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TipService();
    }

    // ─────────────────────────────────────────────────────────────────
    // suggestionsForBooking — basic structure
    // ─────────────────────────────────────────────────────────────────

    public function test_suggestions_returns_three_presets_by_default(): void
    {
        config(['tips.presets' => [
            ['label' => '10%', 'percent' => 10],
            ['label' => '15%', 'percent' => 15],
            ['label' => '20%', 'percent' => 20],
        ]]);

        $booking = new Booking();
        $booking->devis_estime = 100.00;

        $suggestions = $this->service->suggestionsForBooking($booking);

        $this->assertCount(3, $suggestions);
    }

    public function test_each_suggestion_has_required_keys(): void
    {
        config(['tips.presets' => [
            ['label' => '10%', 'percent' => 10],
        ]]);

        $booking = new Booking();
        $booking->devis_estime = 80.00;

        $suggestions = $this->service->suggestionsForBooking($booking);

        $this->assertArrayHasKey('label', $suggestions[0]);
        $this->assertArrayHasKey('percent', $suggestions[0]);
        $this->assertArrayHasKey('amount_cents', $suggestions[0]);
        $this->assertArrayHasKey('amount_formatted', $suggestions[0]);
    }

    // ─────────────────────────────────────────────────────────────────
    // suggestionsForBooking — amount calculation
    // ─────────────────────────────────────────────────────────────────

    public function test_10_percent_preset_calculates_correct_amount(): void
    {
        config(['tips.presets' => [
            ['label' => '10%', 'percent' => 10],
        ]]);

        $booking = new Booking();
        $booking->devis_estime = 100.00; // 10 000 cents

        $suggestions = $this->service->suggestionsForBooking($booking);

        $this->assertSame(1000, $suggestions[0]['amount_cents']);
        $this->assertSame(10, $suggestions[0]['percent']);
    }

    public function test_15_percent_preset_calculates_correct_amount(): void
    {
        config(['tips.presets' => [
            ['label' => '15%', 'percent' => 15],
        ]]);

        $booking = new Booking();
        $booking->devis_estime = 100.00; // 10 000 cents → 15% = 1 500 cents

        $suggestions = $this->service->suggestionsForBooking($booking);

        $this->assertSame(1500, $suggestions[0]['amount_cents']);
    }

    public function test_20_percent_preset_calculates_correct_amount(): void
    {
        config(['tips.presets' => [
            ['label' => '20%', 'percent' => 20],
        ]]);

        $booking = new Booking();
        $booking->devis_estime = 100.00; // 10 000 cents → 20% = 2 000 cents

        $suggestions = $this->service->suggestionsForBooking($booking);

        $this->assertSame(2000, $suggestions[0]['amount_cents']);
    }

    public function test_amounts_are_rounded_correctly_for_fractional_booking(): void
    {
        config(['tips.presets' => [
            ['label' => '10%', 'percent' => 10],
        ]]);

        $booking = new Booking();
        $booking->devis_estime = 33.33; // 3 333 cents → 10% = 333.3 → rounds to 333

        $suggestions = $this->service->suggestionsForBooking($booking);

        $this->assertSame(333, $suggestions[0]['amount_cents']);
    }

    // ─────────────────────────────────────────────────────────────────
    // suggestionsForBooking — edge cases
    // ─────────────────────────────────────────────────────────────────

    public function test_returns_empty_array_when_devis_estime_is_zero(): void
    {
        $booking = new Booking();
        $booking->devis_estime = 0.00;

        $suggestions = $this->service->suggestionsForBooking($booking);

        $this->assertEmpty($suggestions);
    }

    public function test_returns_empty_array_when_devis_estime_is_null(): void
    {
        $booking = new Booking();
        $booking->devis_estime = null;

        $suggestions = $this->service->suggestionsForBooking($booking);

        $this->assertEmpty($suggestions);
    }

    public function test_labels_match_configured_preset_labels(): void
    {
        config(['tips.presets' => [
            ['label' => '10%', 'percent' => 10],
            ['label' => '15%', 'percent' => 15],
            ['label' => '20%', 'percent' => 20],
        ]]);

        $booking = new Booking();
        $booking->devis_estime = 50.00;

        $suggestions = $this->service->suggestionsForBooking($booking);

        $this->assertSame('10%', $suggestions[0]['label']);
        $this->assertSame('15%', $suggestions[1]['label']);
        $this->assertSame('20%', $suggestions[2]['label']);
    }

    public function test_amount_formatted_contains_eur_suffix(): void
    {
        config(['tips.presets' => [
            ['label' => '10%', 'percent' => 10],
        ]]);

        $booking = new Booking();
        $booking->devis_estime = 100.00;

        $suggestions = $this->service->suggestionsForBooking($booking);

        $this->assertStringContainsString('EUR', $suggestions[0]['amount_formatted']);
    }

    public function test_custom_single_preset_returns_one_suggestion(): void
    {
        config(['tips.presets' => [
            ['label' => '5%', 'percent' => 5],
        ]]);

        $booking = new Booking();
        $booking->devis_estime = 200.00; // 20 000 cents → 5% = 1 000 cents

        $suggestions = $this->service->suggestionsForBooking($booking);

        $this->assertCount(1, $suggestions);
        $this->assertSame(1000, $suggestions[0]['amount_cents']);
    }

    public function test_large_booking_amount_does_not_overflow(): void
    {
        config(['tips.presets' => [
            ['label' => '20%', 'percent' => 20],
        ]]);

        $booking = new Booking();
        $booking->devis_estime = 9999.99; // ~999 999 cents → 20% = ~200 000 cents

        $suggestions = $this->service->suggestionsForBooking($booking);

        $this->assertCount(1, $suggestions);
        $this->assertIsInt($suggestions[0]['amount_cents']);
        $this->assertGreaterThan(0, $suggestions[0]['amount_cents']);
    }
}

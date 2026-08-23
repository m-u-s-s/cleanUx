<?php

namespace Tests\Feature\Booking;

use App\Services\Booking\RecurringBookingService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Couvre RecurringBookingService : normalizeSettings (incl. */
class RecurringBookingServiceCoverageBatch11Test extends TestCase
{
    private RecurringBookingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RecurringBookingService;
    }

    public function test_normalize_settings_with_explicit_frequency(): void
    {
        $settings = $this->service->normalizeSettings([
            'recurrence_frequency' => 'weekly',
            'recurrence_interval' => 3,
            'recurrence_until' => '2026-08-01',
            'recurrence_count' => '5',
            'recurrence_days' => ['mon', 'wed'],
        ]);

        $this->assertSame('weekly', $settings['frequency']);
        $this->assertSame(3, $settings['interval']);
        $this->assertSame(5, $settings['count']);
        $this->assertSame([1, 3], $settings['days']);
        $this->assertNotNull($settings['until']);
        $this->assertTrue($settings['until']->isStartOfDay());
    }

    public function test_normalize_settings_clamps_interval_and_nulls_count(): void
    {
        $settings = $this->service->normalizeSettings([
            'recurrence_frequency' => 'daily',
            'recurrence_interval' => 99,
        ]);

        $this->assertSame(12, $settings['interval']);
        $this->assertNull($settings['count']);
        $this->assertNull($settings['until']);
    }

    public function test_normalize_settings_clamps_interval_min(): void
    {
        $settings = $this->service->normalizeSettings([
            'recurrence_frequency' => 'daily',
            'recurrence_interval' => 0,
        ]);

        $this->assertSame(1, $settings['interval']);
    }

    public function test_normalize_settings_maps_legacy_biweekly(): void
    {
        $settings = $this->service->normalizeSettings([
            'recurrence_rule' => 'biweekly',
        ]);

        $this->assertSame('weekly', $settings['frequency']);
        $this->assertSame(2, $settings['interval']);
    }

    public function test_normalize_settings_maps_legacy_monthly(): void
    {
        $settings = $this->service->normalizeSettings([
            'recurrence_rule' => 'monthly',
        ]);

        $this->assertSame('monthly', $settings['frequency']);
        $this->assertSame(1, $settings['interval']);
    }

    public function test_normalize_settings_maps_legacy_default(): void
    {
        $settings = $this->service->normalizeSettings([
            'recurrence_rule' => 'something-else',
        ]);

        $this->assertSame('weekly', $settings['frequency']);
        $this->assertSame(1, $settings['interval']);
    }

    public function test_normalize_settings_uses_fallback_date_for_days(): void
    {
        // 2026-06-08 is a Monday -> isoWeekday 1.
        $settings = $this->service->normalizeSettings([
            'recurrence_frequency' => 'weekly',
        ], '2026-06-08');

        $this->assertSame([1], $settings['days']);
    }

    public function test_validate_settings_rejects_invalid_frequency(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->validateSettings(['frequency' => 'yearly'], '2026-06-08');
    }

    public function test_validate_settings_requires_until_or_count(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->validateSettings(['frequency' => 'weekly'], '2026-06-08');
    }

    public function test_validate_settings_rejects_too_many_occurrences(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->validateSettings([
            'frequency' => 'weekly',
            'count' => 53,
        ], '2026-06-08');
    }

    public function test_validate_settings_rejects_until_before_start(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->validateSettings([
            'frequency' => 'weekly',
            'until' => Carbon::parse('2026-06-01')->startOfDay(),
        ], '2026-06-08');
    }

    public function test_validate_settings_rejects_until_beyond_12_months(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->validateSettings([
            'frequency' => 'weekly',
            'until' => Carbon::parse('2027-12-01')->startOfDay(),
        ], '2026-06-08');
    }

    public function test_validate_settings_passes_with_count(): void
    {
        $this->service->validateSettings([
            'frequency' => 'weekly',
            'count' => 10,
        ], '2026-06-08');

        $this->assertTrue(true);
    }

    public function test_validate_settings_passes_with_until_in_range(): void
    {
        $this->service->validateSettings([
            'frequency' => 'monthly',
            'until' => Carbon::parse('2026-09-08')->startOfDay(),
        ], '2026-06-08');

        $this->assertTrue(true);
    }

    public function test_generate_daily_occurrences_with_count(): void
    {
        $occurrences = $this->service->generateOccurrences('2026-06-08', '09:00', [
            'frequency' => 'daily',
            'interval' => 2,
            'count' => 3,
        ]);

        $this->assertCount(3, $occurrences);
        $this->assertSame('2026-06-08', $occurrences[0]['date']);
        $this->assertSame('09:00', $occurrences[0]['heure']);
        $this->assertSame('2026-06-10', $occurrences[1]['date']);
        $this->assertSame('2026-06-12', $occurrences[2]['date']);
    }

    public function test_generate_daily_occurrences_stops_at_until(): void
    {
        $occurrences = $this->service->generateOccurrences('2026-06-08', '09:00', [
            'frequency' => 'daily',
            'interval' => 1,
            'until' => Carbon::parse('2026-06-10')->startOfDay(),
        ]);

        $this->assertCount(3, $occurrences);
        $this->assertSame('2026-06-10', end($occurrences)['date']);
    }

    public function test_generate_monthly_occurrences_with_count(): void
    {
        $occurrences = $this->service->generateOccurrences('2026-01-31', '08:30', [
            'frequency' => 'monthly',
            'interval' => 1,
            'count' => 3,
        ]);

        $this->assertCount(3, $occurrences);
        $this->assertSame('2026-01-31', $occurrences[0]['date']);
        // addMonthsNoOverflow keeps within month bounds.
        $this->assertSame('2026-02-28', $occurrences[1]['date']);
        $this->assertSame('08:30', $occurrences[1]['heure']);
    }

    public function test_generate_monthly_occurrences_stops_at_until(): void
    {
        $occurrences = $this->service->generateOccurrences('2026-06-08', '10:00', [
            'frequency' => 'monthly',
            'interval' => 1,
            'until' => Carbon::parse('2026-08-08')->startOfDay(),
        ]);

        $this->assertCount(3, $occurrences);
    }

    public function test_generate_weekly_occurrences_with_days(): void
    {
        // Start Monday 2026-06-08, days Mon(1) & Wed(3), interval 1.
        $occurrences = $this->service->generateOccurrences('2026-06-08', '14:00', [
            'frequency' => 'weekly',
            'interval' => 1,
            'count' => 4,
            'days' => [1, 3],
        ]);

        $this->assertCount(4, $occurrences);
        $this->assertSame('2026-06-08', $occurrences[0]['date']);
        $this->assertSame('2026-06-10', $occurrences[1]['date']);
        $this->assertSame('2026-06-15', $occurrences[2]['date']);
        $this->assertSame('14:00', $occurrences[0]['heure']);
    }

    public function test_generate_weekly_occurrences_uses_start_weekday_when_no_days(): void
    {
        $occurrences = $this->service->generateOccurrences('2026-06-08', '14:00', [
            'frequency' => 'weekly',
            'interval' => 1,
            'count' => 2,
            'days' => [],
        ]);

        $this->assertCount(2, $occurrences);
        $this->assertSame('2026-06-08', $occurrences[0]['date']);
        $this->assertSame('2026-06-15', $occurrences[1]['date']);
    }

    public function test_generate_weekly_occurrences_with_interval_two_stops_at_until(): void
    {
        $occurrences = $this->service->generateOccurrences('2026-06-08', '09:00', [
            'frequency' => 'weekly',
            'interval' => 2,
            'until' => Carbon::parse('2026-07-06')->startOfDay(),
            'days' => [1],
        ]);

        // Mondays every 2 weeks within range: 06-08, 06-22, 07-06.
        $this->assertSame(['2026-06-08', '2026-06-22', '2026-07-06'], array_column($occurrences, 'date'));
    }

    public function test_normalize_days_numeric_and_clamped(): void
    {
        $this->assertSame([1, 7], $this->service->normalizeDays([0, 9, 1]));
    }

    public function test_normalize_days_labels(): void
    {
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], $this->service->normalizeDays([
            'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun',
        ]));
    }

    public function test_normalize_days_invalid_label_filtered(): void
    {
        $this->assertSame([], $this->service->normalizeDays(['xyz']));
    }

    public function test_normalize_days_single_string(): void
    {
        $this->assertSame([5], $this->service->normalizeDays('fri'));
    }

    public function test_normalize_days_fallback_when_empty(): void
    {
        // 2026-06-10 is a Wednesday -> 3.
        $this->assertSame([3], $this->service->normalizeDays(null, '2026-06-10'));
    }

    public function test_normalize_days_empty_without_fallback(): void
    {
        $this->assertSame([], $this->service->normalizeDays(null));
    }

    public function test_to_legacy_rule_variants(): void
    {
        $this->assertSame('biweekly', $this->service->toLegacyRule(['frequency' => 'weekly', 'interval' => 2]));
        $this->assertSame('weekly', $this->service->toLegacyRule(['frequency' => 'weekly', 'interval' => 1]));
        $this->assertSame('monthly', $this->service->toLegacyRule(['frequency' => 'monthly']));
        $this->assertSame('daily', $this->service->toLegacyRule(['frequency' => 'daily']));
        $this->assertNull($this->service->toLegacyRule(['frequency' => 'unknown']));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\RecurringBookingSeries;
use Carbon\Carbon;
use Database\Factories\RecurringBookingSeriesFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessRecurringBookingsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_reports_when_no_series_due(): void
    {
        // Series due in the future — not picked up by the `<= now()` filter.
        RecurringBookingSeriesFactory::new()->create([
            'next_occurrence_at' => now()->addWeek(),
            'status' => RecurringBookingSeries::STATUS_ACTIVE,
        ]);

        $this->artisan('bookings:process-recurring')
            ->expectsOutputToContain('No recurring series due')
            ->assertExitCode(0);

        $this->assertSame(0, Booking::query()->count());
    }

    public function test_dry_run_lists_due_series_without_creating_anything(): void
    {
        $series = RecurringBookingSeriesFactory::new()->create([
            'next_occurrence_at' => now()->subDay(),
            'status' => RecurringBookingSeries::STATUS_ACTIVE,
        ]);

        $this->artisan('bookings:process-recurring --dry-run')
            ->expectsOutputToContain('(dry-run)')
            ->expectsOutputToContain("[dry] Series #{$series->id}")
            ->assertExitCode(0);

        $this->assertSame(0, Booking::query()->count());
        $this->assertSame(0, Mission::query()->count());

        // Dry-run never advances the occurrence.
        $series->refresh();
        $this->assertNull($series->last_generated_at);
    }

    public function test_paused_series_is_not_processed(): void
    {
        RecurringBookingSeriesFactory::new()->paused()->create([
            'next_occurrence_at' => now()->subDay(),
        ]);

        $this->artisan('bookings:process-recurring')
            ->expectsOutputToContain('No recurring series due')
            ->assertExitCode(0);

        $this->assertSame(0, Booking::query()->count());
    }

    public function test_due_series_failure_is_isolated_and_reported(): void
    {
        // A due, active series IS picked up and processSeries() runs inside a
        // DB transaction. Mission creation derives planned_start_at from the
        // booking's cast scheduled_date/scheduled_time attributes, which the
        // command concatenates — exercising createBookingFromSeries() and
        // createMissionForBooking(). Any failure is caught, logged and counted
        // (the transaction rolls the partial booking back), and handle()
        // returns FAILURE.
        $series = RecurringBookingSeriesFactory::new()->create([
            'frequency' => 'weekly',
            'interval' => 1,
            'next_occurrence_at' => now()->subDay()->setTime(9, 0),
            'status' => RecurringBookingSeries::STATUS_ACTIVE,
        ]);

        $originalOccurrence = Carbon::parse($series->next_occurrence_at)->toDateTimeString();

        $this->artisan('bookings:process-recurring --limit=10')
            ->expectsOutputToContain('Found 1 due series')
            ->expectsOutputToContain('Done.')
            ->assertExitCode(1);

        // Transaction rollback: no booking/mission persisted on failure.
        $this->assertSame(0, Booking::query()->where('recurring_booking_series_id', $series->id)->count());
        $this->assertSame(0, Mission::query()->count());

        // The series is left untouched so a later run can retry it.
        $series->refresh();
        $this->assertSame(RecurringBookingSeries::STATUS_ACTIVE, $series->status);
        $this->assertSame($originalOccurrence, Carbon::parse($series->next_occurrence_at)->toDateTimeString());
        $this->assertNull($series->last_generated_at);
    }
}

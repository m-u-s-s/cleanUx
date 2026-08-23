<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\RecurringBookingSeries;
use App\Services\Dispatch\MissionDispatchService;
use Carbon\Carbon;
use Database\Factories\RecurringBookingSeriesFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
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

    /** UN ECHEC EST ISOLE, JOURNALISE, COMPTE, ET LA TRANSACTION EST ANNULEE. */
    public function test_due_series_failure_is_isolated_and_reported(): void
    {
        $series = RecurringBookingSeriesFactory::new()->create([
            'frequency' => 'weekly',
            'interval' => 1,
            'next_occurrence_at' => now()->subDay()->setTime(9, 0),
            'status' => RecurringBookingSeries::STATUS_ACTIVE,
        ]);

        $originalOccurrence = Carbon::parse($series->next_occurrence_at)->toDateTimeString();

        // La recherche de candidat est la derniere etape de la transaction : la faire echouer
        // prouve que TOUT ce qui precede est annule.
        $this->mock(MissionDispatchService::class, function ($mock) {
            $mock->shouldReceive('dispatchToNextProvider')
                ->andThrow(new RuntimeException('aucun prestataire joignable'));
        });

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

    /** LE TEMOIN QUI MANQUAIT : le chemin nominal produit VRAIMENT une reservation. */
    public function test_a_due_series_actually_creates_a_booking(): void
    {
        $series = RecurringBookingSeriesFactory::new()->create([
            'frequency' => 'weekly',
            'interval' => 1,
            'next_occurrence_at' => now()->subDay()->setTime(9, 0),
            'status' => RecurringBookingSeries::STATUS_ACTIVE,
        ]);

        $this->artisan('bookings:process-recurring --limit=10')->assertExitCode(0);

        $this->assertSame(1, Booking::query()->where('recurring_booking_series_id', $series->id)->count());

        // Et l'echeance avance : sans cela, la meme reservation repartirait a chaque passage.
        $this->assertTrue(
            Carbon::parse($series->refresh()->next_occurrence_at)->greaterThan(now()),
        );
    }
}

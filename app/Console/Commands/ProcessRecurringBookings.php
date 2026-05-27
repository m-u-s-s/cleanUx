<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\RecurringBookingSeries;
use App\Services\Dispatch\MissionDispatchService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Process recurring booking series whose next occurrence is due today or earlier.
 *
 * For each active series:
 *   1. Create a Booking from the series template_payload
 *   2. Create a Mission linked to that booking
 *   3. Trigger dispatch to the next available provider
 *   4. Advance next_occurrence_at to the following slot
 *
 * Run daily via the scheduler (see app/Console/Kernel.php).
 */
class ProcessRecurringBookings extends Command
{
    protected $signature = 'bookings:process-recurring
                            {--dry-run : List due series without creating bookings}
                            {--limit=100 : Max series to process per run}';

    protected $description = 'Auto-dispatch bookings for recurring series whose next occurrence is due.';

    public function __construct(protected MissionDispatchService $dispatch)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $limit    = (int) $this->option('limit');

        $dueSeries = RecurringBookingSeries::query()
            ->where('status', RecurringBookingSeries::STATUS_ACTIVE)
            ->where('next_occurrence_at', '<=', now())
            ->whereNotNull('next_occurrence_at')
            ->orderBy('next_occurrence_at')
            ->limit($limit)
            ->get();

        if ($dueSeries->isEmpty()) {
            $this->info('No recurring series due. Nothing to do.');
            return Command::SUCCESS;
        }

        $this->info("Found {$dueSeries->count()} due series" . ($isDryRun ? ' (dry-run)' : '') . '.');

        $created = 0;
        $failed  = 0;

        foreach ($dueSeries as $series) {
            if ($isDryRun) {
                $this->line("  [dry] Series #{$series->id} due at {$series->next_occurrence_at}");
                continue;
            }

            try {
                $this->processSeries($series);
                $created++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error('[recurring] Failed to process series', [
                    'series_id' => $series->id,
                    'error'     => $e->getMessage(),
                ]);
                $this->warn("  Series #{$series->id} failed: {$e->getMessage()}");
            }
        }

        $this->info("Done. Created: {$created}, Failed: {$failed}.");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function processSeries(RecurringBookingSeries $series): void
    {
        DB::transaction(function () use ($series) {
            $booking = $this->createBookingFromSeries($series);
            $mission = $this->createMissionForBooking($booking, $series);

            // Trigger dispatch — soft-fail if no candidate found
            $assignment = $this->dispatch->dispatchToNextProvider($mission);

            Log::info('[recurring] Booking created from series', [
                'series_id'     => $series->id,
                'booking_id'    => $booking->id,
                'mission_id'    => $mission->id,
                'assignment_id' => $assignment?->id,
            ]);

            $this->advanceNextOccurrence($series);
        });
    }

    private function createBookingFromSeries(RecurringBookingSeries $series): Booking
    {
        $payload = $series->template_payload ?? [];
        $scheduledDate = Carbon::parse($series->next_occurrence_at)->toDateString();
        $scheduledTime = Carbon::parse($series->next_occurrence_at)->format('H:i');

        return Booking::create(array_merge($payload, [
            'recurring_booking_series_id' => $series->id,
            'recurring_series_id'         => $series->id,
            'is_recurrent'                => true,
            'customer_user_id'            => $series->customer_user_id,
            'customer_organization_id'    => $series->customer_organization_id,
            'organization_site_id'        => $series->organization_site_id,
            'service_catalog_id'          => $series->service_catalog_id,
            'service_zone_id'             => $series->service_zone_id,
            'scheduled_date'              => $scheduledDate,
            'scheduled_time'              => $scheduledTime,
            'status'                      => 'pending',
            'created_by'                  => null,
        ]));
    }

    private function createMissionForBooking(Booking $booking, RecurringBookingSeries $series): Mission
    {
        $plannedStart = null;
        if ($booking->scheduled_date && $booking->scheduled_time) {
            $plannedStart = $booking->scheduled_date . ' ' . $booking->scheduled_time;
        }

        return Mission::create([
            'booking_id'       => $booking->id,
            'status'           => 'planned',
            'planned_start_at' => $plannedStart,
        ]);
    }

    private function advanceNextOccurrence(RecurringBookingSeries $series): void
    {
        $next = $this->computeNextOccurrence($series);

        $shouldDeactivate = $this->seriesIsExhausted($series, $next);

        $series->update([
            'last_generated_at' => now(),
            'next_occurrence_at' => $shouldDeactivate ? null : $next?->toDateTimeString(),
            'status' => $shouldDeactivate ? RecurringBookingSeries::STATUS_CANCELLED : $series->status,
        ]);
    }

    private function computeNextOccurrence(RecurringBookingSeries $series): ?Carbon
    {
        $current = Carbon::parse($series->next_occurrence_at);
        $interval = max(1, (int) ($series->interval ?? 1));

        return match ($series->frequency) {
            'daily'   => $current->addDays($interval),
            'monthly' => $current->addMonthsNoOverflow($interval),
            default   => $current->addWeeks($interval), // weekly
        };
    }

    private function seriesIsExhausted(RecurringBookingSeries $series, ?Carbon $next): bool
    {
        if (! $next) {
            return true;
        }
        if ($series->ends_at && $next->startOfDay()->gt(Carbon::parse($series->ends_at)->startOfDay())) {
            return true;
        }
        return false;
    }
}

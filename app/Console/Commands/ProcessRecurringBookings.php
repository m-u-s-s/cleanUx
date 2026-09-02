<?php

namespace App\Console\Commands;

use App\Jobs\Missions\GeocodeMissionDestination;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\RecurringBookingSeries;
use App\Models\ServiceCatalog;
use App\Services\Contracts\ContractSlaService;
use App\Services\Dispatch\MissionDispatchService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Process recurring booking series whose next occurrence is due today or earlier. */
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
        $limit = (int) $this->option('limit');

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

        $this->info("Found {$dueSeries->count()} due series".($isDryRun ? ' (dry-run)' : '').'.');

        $created = 0;
        $failed = 0;

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
                    'error' => $e->getMessage(),
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
                'series_id' => $series->id,
                'booking_id' => $booking->id,
                'mission_id' => $mission->id,
                'assignment_id' => $assignment?->id,
            ]);

            $this->advanceNextOccurrence($series);
        });
    }

    private function createBookingFromSeries(RecurringBookingSeries $series): Booking
    {
        $payload = (array) ($series->template_payload ?? []);
        $scheduledDate = Carbon::parse($series->next_occurrence_at)->toDateString();
        $scheduledTime = Carbon::parse($series->next_occurrence_at)->format('H:i');

        // LE PAYLOAD DU MODÈLE N'EST PAS UNE LIGNE DE RÉSERVATION.
        $duree = (int) ($payload['duration_minutes'] ?? 0);

        if ($duree > 0) {
            $payload['estimated_duration_minutes'] = $duree;
        }

        $payload = Arr::only($payload, (new Booking)->getFillable());

        return Booking::create(array_merge($payload, [
            'recurring_booking_series_id' => $series->id,
            'recurring_series_id' => $series->id,
            'is_recurrent' => true,
            'customer_user_id' => $series->customer_user_id,
            'customer_organization_id' => $series->customer_organization_id,
            'organization_site_id' => $series->organization_site_id,
            'service_catalog_id' => $series->service_catalog_id,
            // Le metier suit le service, comme partout ailleurs.
            'trade_id' => optional(ServiceCatalog::find($series->service_catalog_id))->trade_id,
            'service_zone_id' => $series->service_zone_id,
            'scheduled_date' => $scheduledDate,
            'scheduled_time' => $scheduledTime,
            'status' => 'pending',
            'created_by' => null,
        ]));
    }

    private function createMissionForBooking(Booking $booking, RecurringBookingSeries $series): Mission
    {
        // ON DÉCOUPE AVANT DE RECOLLER — la concaténation brute produisait une date impossible.
        $plannedStart = null;

        if ($booking->scheduled_date && $booking->scheduled_time) {
            // On FORMATE chaque moitié au lieu de laisser la conversion en chaîne s'en charger : les deux colonnes sont castées, chacune se rend donc comme un instant COMPLET, et c'est leur date redondante qui produisait la double spécification.
            $plannedStart = $booking->scheduled_date->format('Y-m-d')
                .' '.$booking->scheduled_time->format('H:i:s');
        }

        $mission = Mission::create([
            'booking_id' => $booking->id,
            'status' => 'planned',
            'planned_start_at' => $plannedStart,
            // SP1 Task 5 : recopie les champs prestataire/org du booking (souvent
            // null à la création — complétés ensuite par le dispatch).
            'lead_provider_user_id' => $booking->assigned_provider_user_id,
            'lead_employee_id' => $booking->assigned_provider_user_id,
            'provider_organization_id' => $booking->assigned_provider_organization_id,
            'provider_team_id' => $booking->provider_team_id,
            'organization_account_id' => $booking->customer_organization_id,
        ]);

        // SP4 — propage le contrat du booking à la mission + arme le SLA (soft).
        if ($booking->organization_contract_id) {
            $mission->forceFill(['organization_contract_id' => $booking->organization_contract_id])->save();
            app(ContractSlaService::class)->armForMission($mission);
        }

        // Même raison que dans CreateBookingFromApiAction : ce chemin ne renseignait aucune
        // destination, donc aucun marqueur sur la carte du prestataire.
        GeocodeMissionDestination::dispatch($mission->id);

        return $mission;
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
            'daily' => $current->addDays($interval),
            'monthly' => $current->addMonthsNoOverflow($interval),
            default => $current->addWeeks($interval), // weekly
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

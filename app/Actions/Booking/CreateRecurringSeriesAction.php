<?php

namespace App\Actions\Booking;

use App\Data\ZoneCoverageResult;
use App\Models\OrganizationSite;
use App\Models\PostalCode;
use App\Models\RendezVous;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\User;
use App\Models\ZoneServiceRule;
use App\Services\Booking\CreateBookingAction;
use App\Services\Booking\EmployeeAvailabilityService;
use App\Services\Booking\RecurringBookingService;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateRecurringSeriesAction
{
    public function __construct(
        protected CreateBookingAction $bookingAction,
        protected EmployeeAvailabilityService $employeeAvailabilityService,
        protected RecurringBookingService $recurringBookingService,
    ) {
    }

    public function execute(
        User $client,
        PostalCode $postal,
        ServiceZone $zone,
        ServiceCatalog $catalog,
        ZoneServiceRule $rule,
        array $data,
        ?OrganizationSite $organizationSite = null,
        ?ZoneCoverageResult $resolution = null,
    ): array {
        $settings = $this->recurringBookingService->normalizeSettings($data, Arr::get($data, 'date'));
        $this->recurringBookingService->validateSettings($settings, (string) Arr::get($data, 'date'));

        $occurrences = $this->recurringBookingService->generateOccurrences(
            (string) Arr::get($data, 'date'),
            (string) Arr::get($data, 'heure'),
            $settings,
        );

        if (count($occurrences) < 2) {
            throw ValidationException::withMessages([
                'recurrence_count' => 'La récurrence doit générer au moins 2 occurrences.',
            ]);
        }

        $seriesId = (string) Str::uuid();
        $seriesReference = Arr::get($data, 'booking_reference', 'CUXR-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)));
        $estimatedDuration = max(30, (int) (Arr::get($data, 'duree_estimee') ?: 90));
        $manualEmployeeId = Arr::get($data, 'employe_id');
        $plannedEmployees = [];
        $zoneLoads = [];
        $serviceLoads = [];

        foreach ($occurrences as $index => $occurrence) {
            $date = $occurrence['date'];
            $heure = $occurrence['heure'];

            $this->assertMinimumNotice($date, $heure, $zone, $rule);
            $this->assertCapacity($date, $zone, $catalog, $rule, $zoneLoads, $serviceLoads);

            $assignedEmployee = null;

            if ($manualEmployeeId) {
                $assignedEmployee = User::query()->find($manualEmployeeId);

                if (! $assignedEmployee || ! $this->employeeAvailabilityService->employeeCanCoverZone((int) $manualEmployeeId, (int) $zone->id)) {
                    throw ValidationException::withMessages([
                        'employe_id' => 'Cet employé ne couvre pas la zone pour toute la série.',
                    ]);
                }

                if (! $this->employeeAvailabilityService->employeeIsAvailableForSlot((int) $manualEmployeeId, $date, $heure, $zone, $estimatedDuration)) {
                    throw ValidationException::withMessages([
                        'rdvHeure' => 'Le créneau '.$date.' '.$heure.' n’est pas disponible pour cet employé.',
                    ]);
                }
            } else {
                $assignedEmployee = $this->employeeAvailabilityService->resolveBestAvailableEmployeeForSlot($date, $heure, $zone, $estimatedDuration);

                if (! $assignedEmployee) {
                    throw ValidationException::withMessages([
                        'rdvHeure' => 'Aucun employé disponible ne couvre toute la série.',
                    ]);
                }
            }

            $plannedEmployees[$index] = $assignedEmployee;
            $zoneLoads[$date] = ($zoneLoads[$date] ?? 0) + 1;
            $serviceLoads[$date] = ($serviceLoads[$date] ?? 0) + 1;
        }

        $created = collect();

        foreach ($occurrences as $index => $occurrence) {
            $occurrenceData = array_merge($data, [
                'booking_reference' => sprintf('%s-%02d', $seriesReference, $index + 1),
                'date' => $occurrence['date'],
                'heure' => $occurrence['heure'],
                'is_recurrent' => true,
                'recurrence_rule' => $this->recurringBookingService->toLegacyRule($settings),
                'recurring_series_id' => $seriesId,
                'recurrence_frequency' => $settings['frequency'],
                'recurrence_interval' => $settings['interval'],
                'recurrence_until' => $settings['until']?->toDateString(),
                'recurrence_count' => $settings['count'],
                'recurrence_days' => $settings['days'],
                'is_series_master' => $index === 0,
                'series_position' => $index + 1,
                'series_status' => 'active',
            ]);

            $created->push($this->bookingAction->execute(
                client: $client,
                postal: $postal,
                zone: $zone,
                catalog: $catalog,
                rule: $rule,
                assignedEmployee: $plannedEmployees[$index],
                data: $occurrenceData,
                organizationSite: $organizationSite,
                resolution: $resolution,
            ));
        }

        return [
            'master' => $created->first(),
            'occurrences' => $created,
            'series_id' => $seriesId,
            'series_reference' => $seriesReference,
            'occurrences_count' => $created->count(),
        ];
    }

    protected function assertMinimumNotice(string $date, string $heure, ServiceZone $zone, ZoneServiceRule $rule): void
    {
        $minimumNoticeHours = max((int) ($zone->minimum_notice_hours ?? 0), (int) ($rule->minimum_notice_hours ?? 0));
        $requestedAt = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$heure, config('app.timezone', 'Europe/Brussels'));

        if ($requestedAt->lt(now(config('app.timezone', 'Europe/Brussels'))->addHours($minimumNoticeHours))) {
            throw ValidationException::withMessages([
                'recurrence_until' => 'Au moins une occurrence ne respecte pas le délai minimum de réservation.',
            ]);
        }
    }

    protected function assertCapacity(string $date, ServiceZone $zone, ServiceCatalog $catalog, ZoneServiceRule $rule, array $zoneLoads, array $serviceLoads): void
    {
        $activeStatuses = ['en_attente', 'confirme', 'en_route', 'sur_place'];

        $existingZone = RendezVous::query()
            ->where('service_zone_id', $zone->id)
            ->whereDate('date', $date)
            ->whereIn('status', $activeStatuses)
            ->count();

        if ($zone->maximum_daily_jobs && ($existingZone + ($zoneLoads[$date] ?? 0)) >= (int) $zone->maximum_daily_jobs) {
            throw ValidationException::withMessages([
                'recurrence_count' => 'La capacité journalière de la zone est dépassée pour le '.$date.'.',
            ]);
        }

        $existingService = RendezVous::query()
            ->where('service_zone_id', $zone->id)
            ->where('service_catalog_id', $catalog->id)
            ->whereDate('date', $date)
            ->whereIn('status', $activeStatuses)
            ->count();

        if ($rule->maximum_daily_capacity && ($existingService + ($serviceLoads[$date] ?? 0)) >= (int) $rule->maximum_daily_capacity) {
            throw ValidationException::withMessages([
                'recurrence_count' => 'La capacité du service est dépassée pour le '.$date.'.',
            ]);
        }
    }
}

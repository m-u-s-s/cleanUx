<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Support\Collection;

class PreferredCompanyResolver
{
    public function __construct(protected EmployeeAvailabilityService $availability) {}

    /**
     * @return array{status:string, provider:?User, alternative_slots:list<array{date:string, heure:string}>}
     */
    public function resolve(Booking $rdv): array
    {
        $none = ['status' => 'none', 'provider' => null, 'alternative_slots' => []];
        $orgId = $rdv->assigned_provider_organization_id;
        if (! $orgId || ! $rdv->service_zone_id || ! $rdv->date || ! $rdv->heure) {
            return $none;
        }

        $type = $rdv->provider_type_preference ?: 'company';
        $duration = (int) ($rdv->estimated_duration_minutes ?: $rdv->duree ?: 90);
        $zone = $rdv->serviceZone instanceof ServiceZone ? $rdv->serviceZone : null;
        $date = $rdv->date->format('Y-m-d');
        $heure = substr((string) $rdv->heure, 0, 5);

        /** @var Collection<int, User> $candidates */
        $candidates = $this->availability->sortedEligibleEmployeesForZone((int) $rdv->service_zone_id, $type, (int) $orgId);

        $available = $candidates->first(fn (User $w) => $this->availability->employeeIsAvailableForSlot(
            $w->id, $date, $heure, $zone, $duration, $rdv->id
        ));

        if ($available) {
            return ['status' => 'assigned', 'provider' => $available, 'alternative_slots' => []];
        }

        return ['status' => 'unavailable', 'provider' => null, 'alternative_slots' => $this->slots($candidates, $rdv, $duration, $zone)];
    }

    /**
     * @param  Collection<int, User>  $candidates
     * @return list<array{date:string, heure:string}>
     */
    private function slots(Collection $candidates, Booking $rdv, int $duration, ?ServiceZone $zone): array
    {
        $slots = [];
        $start = $rdv->date->copy()->startOfDay();
        for ($d = 0; $d < 7 && count($slots) < 5; $d++) {
            $day = $start->copy()->addDays($d);
            foreach (['09:00', '11:00', '14:00', '16:00'] as $heure) {
                if (count($slots) >= 5) {
                    break;
                }
                $hit = $candidates->first(fn (User $w) => $this->availability->employeeIsAvailableForSlot(
                    $w->id, $day->format('Y-m-d'), $heure, $zone, $duration, $rdv->id
                ));
                if ($hit) {
                    $slots[] = ['date' => $day->format('Y-m-d'), 'heure' => $heure];
                }
            }
        }

        return $slots;
    }
}

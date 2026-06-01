<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\ServiceZone;
use App\Models\User;

class PreferredProviderResolver
{
    public function __construct(protected EmployeeAvailabilityService $availability) {}

    /**
     * @return array{status:string, provider:?User, alternative_slots:list<array<string,mixed>>}
     */
    public function resolve(Booking $rdv): array
    {
        $none = ['status' => 'none', 'provider' => null, 'alternative_slots' => []];
        if (! $rdv->preferred_provider_user_id || ! $rdv->service_zone_id || ! $rdv->date || ! $rdv->heure) {
            return $none;
        }

        $provider = User::find($rdv->preferred_provider_user_id);
        if (! $provider) {
            return $none;
        }

        $duration = (int) ($rdv->duree_estimee ?: $rdv->duree ?: 90);
        $zone = $rdv->serviceZone instanceof ServiceZone ? $rdv->serviceZone : null;
        $available = $this->availability->employeeIsAvailableForSlot(
            $provider->id,
            $rdv->date->format('Y-m-d'),
            substr((string) $rdv->heure, 0, 5),
            $zone,
            $duration,
            $rdv->id,
        );

        if ($available) {
            return ['status' => 'assigned', 'provider' => $provider, 'alternative_slots' => []];
        }

        return [
            'status' => 'unavailable',
            'provider' => $provider,
            'alternative_slots' => $this->alternativeSlots($provider, $rdv, $duration),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function alternativeSlots(User $provider, Booking $rdv, int $duration): array
    {
        $slots = [];
        $zone = $rdv->serviceZone instanceof ServiceZone ? $rdv->serviceZone : null;
        $start = $rdv->date->copy()->startOfDay();
        for ($d = 0; $d < 7 && count($slots) < 5; $d++) {
            $day = $start->copy()->addDays($d);
            foreach (['09:00', '11:00', '14:00', '16:00'] as $heure) {
                if (count($slots) >= 5) {
                    break;
                }
                if ($this->availability->employeeIsAvailableForSlot(
                    $provider->id, $day->format('Y-m-d'), $heure, $zone, $duration, $rdv->id
                )) {
                    $slots[] = ['date' => $day->format('Y-m-d'), 'heure' => $heure];
                }
            }
        }

        return $slots;
    }
}

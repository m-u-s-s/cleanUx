<?php

namespace App\Services\Missions;

use App\Models\FieldTeam;
use App\Models\FieldTeamLoadSnapshot;
use App\Models\Mission;
use App\Models\MissionBatch;
use App\Models\MissionTaskSegment;
use App\Models\ServicePartner;
use App\Models\ServicePartnerLoadSnapshot;
use Carbon\Carbon;

class OperationalLoadCalculator
{
    public function captureFieldTeamSnapshot(FieldTeam $team, \DateTimeInterface|string|null $forDate = null): FieldTeamLoadSnapshot
    {
        $date = Carbon::parse($forDate ?? now())->toDateString();

        $segments = MissionTaskSegment::query()
            ->where('field_team_id', $team->id)
            ->where(function ($query) use ($date) {
                $query->whereDate('service_date', $date)
                    ->orWhereDate('planned_start_at', $date);
            });

        $plannedSegmentsCount = (clone $segments)->count();
        $plannedMinutes = (int) ((clone $segments)->sum('estimated_minutes') ?: 0);

        $activeMissionsCount = Mission::query()
            ->whereHas('taskSegment', function ($query) use ($team, $date) {
                $query->where('field_team_id', $team->id)
                    ->where(function ($inner) use ($date) {
                        $inner->whereDate('service_date', $date)
                            ->orWhereDate('planned_start_at', $date);
                    });
            })
            ->whereIn('status', ['planned', 'assigned', 'en_route', 'arrived', 'started', 'in_progress'])
            ->count();

        $assignedMembersCount = method_exists($team, 'activeMembers')
            ? $team->activeMembers()->count()
            : max(1, $team->users()->count());

        $capacityPerMember = (int) data_get($team->metadata, 'capacity_minutes_per_member', 480);
        $capacityMinutes = max(1, $assignedMembersCount) * max(60, $capacityPerMember);
        $utilization = $capacityMinutes > 0 ? round(($plannedMinutes / $capacityMinutes) * 100, 2) : 0;

        return FieldTeamLoadSnapshot::query()->updateOrCreate(
            ['field_team_id' => $team->id, 'snapshot_date' => $date],
            [
                'active_missions_count' => $activeMissionsCount,
                'planned_segments_count' => $plannedSegmentsCount,
                'planned_minutes' => $plannedMinutes,
                'assigned_members_count' => $assignedMembersCount,
                'capacity_minutes' => $capacityMinutes,
                'utilization_percent' => $utilization,
                'metadata' => [
                    'max_concurrent_missions' => $team->max_concurrent_missions,
                    'service_zone_id' => $team->service_zone_id,
                ],
            ]
        );
    }

    public function captureServicePartnerSnapshot(ServicePartner $partner, \DateTimeInterface|string|null $forDate = null): ServicePartnerLoadSnapshot
    {
        $date = Carbon::parse($forDate ?? now())->toDateString();

        $segments = MissionTaskSegment::query()
            ->where('service_partner_id', $partner->id)
            ->where(function ($query) use ($date) {
                $query->whereDate('service_date', $date)
                    ->orWhereDate('planned_start_at', $date);
            });

        $plannedSegmentsCount = (clone $segments)->count();
        $plannedMinutes = (int) ((clone $segments)->sum('estimated_minutes') ?: 0);

        $activeMissionsCount = Mission::query()
            ->whereHas('taskSegment', function ($query) use ($partner, $date) {
                $query->where('service_partner_id', $partner->id)
                    ->where(function ($inner) use ($date) {
                        $inner->whereDate('service_date', $date)
                            ->orWhereDate('planned_start_at', $date);
                    });
            })
            ->whereIn('status', ['planned', 'assigned', 'en_route', 'arrived', 'started', 'in_progress'])
            ->count();

        $dailyCapacity = (int) data_get($partner->metadata, 'default_daily_capacity', 480);
        $utilization = $dailyCapacity > 0 ? round(($plannedMinutes / $dailyCapacity) * 100, 2) : 0;

        return ServicePartnerLoadSnapshot::query()->updateOrCreate(
            ['service_partner_id' => $partner->id, 'snapshot_date' => $date],
            [
                'active_missions_count' => $activeMissionsCount,
                'planned_segments_count' => $plannedSegmentsCount,
                'planned_minutes' => $plannedMinutes,
                'daily_capacity' => $dailyCapacity,
                'utilization_percent' => $utilization,
                'metadata' => [
                    'quality_score' => $partner->quality_score,
                ],
            ]
        );
    }

    public function captureDailySnapshots(\DateTimeInterface|string|null $forDate = null): array
    {
        $date = Carbon::parse($forDate ?? now())->toDateString();

        $fieldTeams = FieldTeam::query()->get()->map(fn (FieldTeam $team) => $this->captureFieldTeamSnapshot($team, $date));
        $partners = ServicePartner::query()
            ->where(function ($query) {
                $query->where('is_active', true)->orWhereNull('is_active');
            })
            ->get()
            ->map(fn (ServicePartner $partner) => $this->captureServicePartnerSnapshot($partner, $date));

        return [
            'date' => $date,
            'field_team_snapshots' => $fieldTeams,
            'partner_snapshots' => $partners,
        ];
    }

    public function recommendFieldTeamForBatch(MissionBatch $batch, \DateTimeInterface|string|null $forDate = null): ?FieldTeam
    {
        $date = Carbon::parse($forDate ?? $batch->starts_on ?? now())->toDateString();

        $teams = FieldTeam::query()
            ->when($batch->organization_account_id, fn ($q) => $q->where(function ($inner) use ($batch) {
                $inner->whereNull('organization_account_id')->orWhere('organization_account_id', $batch->organization_account_id);
            }))
            ->when(optional($batch->organizationSite)->service_zone_id, fn ($q, $zoneId) => $q->where(function ($inner) use ($zoneId) {
                $inner->whereNull('service_zone_id')->orWhere('service_zone_id', $zoneId);
            }))
            ->get();

        return $teams->sortBy(function (FieldTeam $team) use ($date) {
            return $this->captureFieldTeamSnapshot($team, $date)->utilization_percent ?? 999;
        })->first();
    }

    public function recommendPartnerForBatch(MissionBatch $batch, \DateTimeInterface|string|null $forDate = null): ?ServicePartner
    {
        $date = Carbon::parse($forDate ?? $batch->starts_on ?? now())->toDateString();

        $partners = ServicePartner::query()
            ->where(function ($query) {
                $query->where('is_active', true)->orWhereNull('is_active');
            })
            ->get();

        return $partners->sortBy(function (ServicePartner $partner) use ($date) {
            return $this->captureServicePartnerSnapshot($partner, $date)->utilization_percent ?? 999;
        })->first();
    }
}

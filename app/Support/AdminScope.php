<?php

namespace App\Support;

use App\Models\Feedback;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AdminScope
{
    public static function scopeRendezVousQuery(Builder $query, ?User $admin): Builder
    {
        if (! $admin?->isAdmin()) {
            return $query;
        }

        if ($admin->isZoneScopedAdmin()) {
            $query->where('service_zone_id', $admin->managed_service_zone_id);
        }

        return $query;
    }

    public static function scopeFeedbackQuery(Builder $query, ?User $admin): Builder
    {
        if (! $admin?->isAdmin()) {
            return $query;
        }

        if ($admin->isZoneScopedAdmin()) {
            $query->whereHas('rendezVous', function (Builder $rendezVousQuery) use ($admin) {
                $rendezVousQuery->where('service_zone_id', $admin->managed_service_zone_id);
            });
        }

        return $query;
    }

    public static function scopeUserQuery(Builder $query, ?User $admin): Builder
    {
        if (! $admin?->isAdmin()) {
            return $query;
        }

        if ($admin->isZoneScopedAdmin()) {
            $zoneId = (int) $admin->managed_service_zone_id;

            $query->where(function (Builder $scoped) use ($zoneId, $admin) {
                $scoped->whereKey($admin->id)
                    ->orWhere('primary_service_zone_id', $zoneId)
                    ->orWhere('managed_service_zone_id', $zoneId)
                    ->orWhereHas('zoneAssignments', function (Builder $assignmentQuery) use ($zoneId) {
                        $assignmentQuery
                            ->where('service_zone_id', $zoneId)
                            ->where('is_active', true);
                    });
            });
        }

        return $query;
    }

    public static function canAccessRendezVous(User $admin, Booking $rendezVous): bool
    {
        if (! $admin->isAdmin() || ! $admin->is_active) {
            return false;
        }

        if ($admin->isZoneScopedAdmin()) {
            return (int) $rendezVous->service_zone_id === (int) $admin->managed_service_zone_id;
        }

        return true;
    }

    public static function canAccessFeedback(User $admin, Feedback $feedback): bool
    {
        if (! $admin->isAdmin() || ! $admin->is_active) {
            return false;
        }

        $feedback->loadMissing('rendezVous');

        if (! $feedback->rendezVous) {
            return ! $admin->isZoneScopedAdmin();
        }

        return self::canAccessRendezVous($admin, $feedback->rendezVous);
    }

    public static function canAccessUser(User $admin, User $target): bool
    {
        if (! $admin->isAdmin() || ! $admin->is_active) {
            return false;
        }

        if ($admin->id === $target->id) {
            return true;
        }

        if (! $admin->isZoneScopedAdmin()) {
            return true;
        }

        $zoneId = (int) $admin->managed_service_zone_id;

        if ((int) $target->primary_service_zone_id === $zoneId) {
            return true;
        }

        if ((int) $target->managed_service_zone_id === $zoneId) {
            return true;
        }

        return $target->zoneAssignments()
            ->where('service_zone_id', $zoneId)
            ->where('is_active', true)
            ->exists();
    }
}

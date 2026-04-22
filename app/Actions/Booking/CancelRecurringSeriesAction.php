<?php

namespace App\Actions\Booking;

use App\Models\RendezVous;
use App\Support\ActivityLogger;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CancelRecurringSeriesAction
{
    public function pause(RendezVous $anchor, string $scope = 'future'): Collection
    {
        return $this->applyStatus($anchor, 'paused', null, $scope, 'booking.recurring.paused');
    }

    public function resume(RendezVous $anchor, string $scope = 'future'): Collection
    {
        return $this->applyStatus($anchor, 'active', null, $scope, 'booking.recurring.resumed');
    }

    public function cancel(RendezVous $anchor, string $scope = 'future'): Collection
    {
        return $this->applyStatus($anchor, 'cancelled', 'refuse', $scope, 'booking.recurring.cancelled');
    }

    protected function applyStatus(RendezVous $anchor, string $seriesStatus, ?string $bookingStatus, string $scope, string $action): Collection
    {
        if (! $anchor->recurring_series_id) {
            throw ValidationException::withMessages([
                'series' => 'Ce rendez-vous n’appartient à aucune série récurrente.',
            ]);
        }

        $targets = $this->resolveTargets($anchor, $scope);

        foreach ($targets as $target) {
            $original = [
                'date' => $target->date,
                'heure' => $target->heure,
                'status' => $target->status,
                'priorite' => $target->priorite,
            ];
            $target->series_status = $seriesStatus;

            if ($bookingStatus) {
                $target->status = $bookingStatus;
            }

            $target->resetNotificationTrackingIfNeeded($original);
            $target->save();
        }

        ActivityLogger::log($action, $anchor, [
            'recurring_series_id' => $anchor->recurring_series_id,
            'scope' => $scope,
            'targets_count' => $targets->count(),
            'target_ids' => $targets->pluck('id')->all(),
            'series_status' => $seriesStatus,
            'booking_status' => $bookingStatus,
        ]);

        return $targets->map->fresh();
    }

    protected function resolveTargets(RendezVous $anchor, string $scope): Collection
    {
        $query = RendezVous::query()
            ->where('recurring_series_id', $anchor->recurring_series_id)
            ->orderBy('series_position')
            ->orderBy('date')
            ->orderBy('heure');

        return match ($scope) {
            'series' => $query->get(),
            'future' => $query->where('series_position', '>=', (int) $anchor->series_position)->get(),
            default => collect([$anchor]),
        };
    }
}

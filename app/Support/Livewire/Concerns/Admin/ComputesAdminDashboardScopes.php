<?php

namespace App\Support\Livewire\Concerns\Admin;

use App\Models\ActivityLog;
use App\Models\RendezVous;
use App\Models\ServiceZone;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

trait ComputesAdminDashboardScopes
{
        protected function refreshFilterCollections(): void
        {
            $this->employes = $this->scopedEmployeesQuery()
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get();

            $this->clients = $this->scopedClientsQuery()
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get();
        }

        protected function currentAdmin(): ?User
        {
            return auth()->user();
        }

        protected function selectedZoneId(): ?int
        {
            return filled($this->filtreZone) ? (int) $this->filtreZone : null;
        }

        protected function scopeZoneIds(): Collection
        {
            $admin = $this->currentAdmin();

            if (! $admin) {
                return collect();
            }

            if ($admin->isZoneScopedAdmin()) {
                return collect([$admin->managed_service_zone_id])->filter()->values();
            }

            return collect([$this->selectedZoneId()])->filter()->values();
        }

        protected function zoneFiltered(): bool
        {
            return $this->scopeZoneIds()->isNotEmpty();
        }

        protected function scopedRendezVousQuery(bool $withEmployeeFilter = true): Builder
        {
            $query = RendezVous::query();

            if ($withEmployeeFilter && $this->filtreEmploye) {
                $query->where('employe_id', $this->filtreEmploye);
            }

            $zoneIds = $this->scopeZoneIds();
            if ($zoneIds->isNotEmpty()) {
                $query->whereIn('service_zone_id', $zoneIds);
            }

            return $query;
        }

        protected function scopedEmployeesQuery(): Builder
        {
            $query = User::query()->where('role', 'employe');
            $zoneIds = $this->scopeZoneIds();

            if ($zoneIds->isNotEmpty()) {
                $query->where(function (Builder $employeeQuery) use ($zoneIds) {
                    $employeeQuery
                        ->whereIn('primary_service_zone_id', $zoneIds)
                        ->orWhereHas('zoneAssignments', function (Builder $assignmentQuery) use ($zoneIds) {
                            $assignmentQuery->whereIn('service_zone_id', $zoneIds)
                                ->where('is_active', true);
                        });
                });
            }

            return $query;
        }

        protected function scopedClientsQuery(): Builder
        {
            $query = User::query()->clientFacing();
            $zoneIds = $this->scopeZoneIds();

            if ($zoneIds->isNotEmpty()) {
                $query->where(function (Builder $clientQuery) use ($zoneIds) {
                    $clientQuery
                        ->whereIn('primary_service_zone_id', $zoneIds)
                        ->orWhereHas('rendezVousClient', function (Builder $bookingQuery) use ($zoneIds) {
                            $bookingQuery->whereIn('service_zone_id', $zoneIds);
                        });
                });
            }

            return $query;
        }

        protected function scopedActivityLogsQuery(): Builder
        {
            $query = ActivityLog::query()->with('user')->latest();
            $zoneIds = $this->scopeZoneIds();

            if ($zoneIds->isEmpty()) {
                return $query;
            }

            $rendezVousIds = $this->scopedRendezVousQuery(false)->pluck('id');
            $employeeIds = $this->scopedEmployeesQuery()->pluck('id');
            $zoneIdsList = $zoneIds->values();

            return $query->where(function (Builder $logQuery) use ($rendezVousIds, $employeeIds, $zoneIdsList) {
                $logQuery
                    ->where(function (Builder $sub) use ($rendezVousIds) {
                        $sub->where('target_type', RendezVous::class)
                            ->whereIn('target_id', $rendezVousIds);
                    })
                    ->orWhere(function (Builder $sub) use ($employeeIds) {
                        $sub->where('target_type', User::class)
                            ->whereIn('target_id', $employeeIds);
                    })
                    ->orWhere(function (Builder $sub) use ($zoneIdsList) {
                        $sub->where('target_type', ServiceZone::class)
                            ->whereIn('target_id', $zoneIdsList);
                    });
            });
        }

        protected function cacheKey(string $suffix): string
        {
            $zonePart = $this->selectedZoneId() ?: ($this->zoneScopeLocked ? 'managed' : 'all');

            return 'admin_dashboard.' . $zonePart . '.' . ($this->filtreEmploye ?: 'all') . '.' . $suffix;
        }

        protected function clearAdminCache(): void
        {
            Cache::forget($this->cacheKey('statistiquesData'));
            Cache::forget($this->cacheKey('statsMensuelles'));
            Cache::forget($this->cacheKey('topServices'));
            Cache::forget($this->cacheKey('topVilles'));
            Cache::forget($this->cacheKey('dureeStats'));
            Cache::forget($this->cacheKey('performanceEmployes'));
            Cache::forget($this->cacheKey('feedbackRate'));
            Cache::forget($this->cacheKey('adminKpis'));
            Cache::forget($this->cacheKey('servicesSousEstimes'));
            Cache::forget($this->cacheKey('zoneOverview'));
        }

        protected function logActivity(string $action, ?RendezVous $rdv = null, array $meta = []): void
        {
            ActivityLogger::log($action, $rdv, $meta);
        }

        public function getAvailableZonesProperty()
        {
            $zoneIds = $this->scopeZoneIds();

            $query = ServiceZone::query()->orderBy('name');

            if ($zoneIds->isNotEmpty()) {
                $query->whereIn('id', $zoneIds);
            }

            return $query->get();
        }

        public function getSelectedZoneProperty()
        {
            return $this->selectedZoneId()
                ? ServiceZone::find($this->selectedZoneId())
                : null;
        }

        public function getAdminScopeLabelProperty(): string
        {
            $admin = $this->currentAdmin();

            if (! $admin) {
                return 'Inconnu';
            }

            if ($admin->isZoneScopedAdmin()) {
                return 'Zone';
            }

            return $admin->isReadOnlyAdmin() ? 'Lecture seule' : 'Global';
        }

        public function getZoneOverviewProperty(): ?array
        {
            if (! $this->zoneFiltered()) {
                return null;
            }

            return Cache::remember($this->cacheKey('zoneOverview'), now()->addMinutes(10), function () {
                $bookings = $this->scopedRendezVousQuery(false);
                $bookingsToday = (clone $bookings)->whereDate('date', today())->count();
                $activeEmployees = $this->scopedEmployeesQuery()->count();
                $clients = $this->scopedClientsQuery()->count();
                $pending = (clone $bookings)->where('status', 'en_attente')->count();

                return [
                    'bookings_today' => $bookingsToday,
                    'active_employees' => $activeEmployees,
                    'clients' => $clients,
                    'pending' => $pending,
                ];
            });
        }

}

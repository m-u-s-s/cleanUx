<?php

namespace App\Livewire\Admin;

use App\Models\CustomerClaim;
use App\Models\FinanceInvoice;
use App\Models\Mission;
use App\Models\RendezVous;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class OperationsAlertsCenter extends Component
{
    public function render(): View
    {
        $now = now();

        $lateMissions = Mission::query()
            ->with(['rendezVous.client', 'leadEmployee'])
            ->whereIn('status', [
                MissionStatus::PLANNED,
                MissionStatus::ASSIGNED,
                MissionStatus::EN_ROUTE,
            ])
            ->whereNotNull('planned_start_at')
            ->where('planned_start_at', '<', $now->copy()->subMinutes(15))
            ->latest('planned_start_at')
            ->limit(10)
            ->get();

        $employeesNotStarted = Mission::query()
            ->with(['rendezVous.client', 'leadEmployee'])
            ->whereIn('status', [
                MissionStatus::PLANNED,
                MissionStatus::ASSIGNED,
            ])
            ->whereNotNull('planned_start_at')
            ->whereBetween('planned_start_at', [
                $now->copy()->subMinutes(30),
                $now->copy()->addMinutes(30),
            ])
            ->latest('planned_start_at')
            ->limit(10)
            ->get();

        $missingEndCode = Mission::query()
            ->with(['rendezVous.client', 'leadEmployee'])
            ->whereIn('status', [
                MissionStatus::STARTED,
                MissionStatus::PAUSED,
            ])
            ->whereNotNull('actual_start_at')
            ->where('actual_start_at', '<', $now->copy()->subHours(4))
            ->latest('actual_start_at')
            ->limit(10)
            ->get();

        $trackingInterrupted = Mission::query()
            ->with(['rendezVous.client', 'leadEmployee', 'activeTrackingSession'])
            ->whereIn('status', [
                MissionStatus::EN_ROUTE,
                MissionStatus::ARRIVED,
                MissionStatus::STARTED,
                MissionStatus::PAUSED,
            ])
            ->whereHas('activeTrackingSession', function ($query) use ($now) {
                $query->where('updated_at', '<', $now->copy()->subMinutes(10));
            })
            ->latest()
            ->limit(10)
            ->get();

        $openClaims = class_exists(CustomerClaim::class)
            ? CustomerClaim::query()
                ->with(['client', 'rendezVous'])
                ->whereIn('status', ['open', 'in_review', 'waiting_client'])
                ->latest()
                ->limit(10)
                ->get()
            : collect();

        $unpaidInvoices = class_exists(FinanceInvoice::class)
            ? FinanceInvoice::query()
                ->with(['client', 'rendezVous'])
                ->whereIn('status', ['unpaid', 'overdue', 'pending'])
                ->latest()
                ->limit(10)
                ->get()
            : collect();

        $pendingBookings = RendezVous::query()
            ->with(['client', 'employe', 'serviceZone'])
            ->where('status', BookingStatus::EN_ATTENTE)
            ->whereDate('date', '<=', $now->copy()->addDays(2))
            ->orderBy('date')
            ->orderBy('heure')
            ->limit(10)
            ->get();

        return view('livewire.admin.operations-alerts-center', [
            'lateMissions' => $lateMissions,
            'employeesNotStarted' => $employeesNotStarted,
            'missingEndCode' => $missingEndCode,
            'trackingInterrupted' => $trackingInterrupted,
            'openClaims' => $openClaims,
            'unpaidInvoices' => $unpaidInvoices,
            'pendingBookings' => $pendingBookings,
            'totalAlerts' =>
                $lateMissions->count()
                + $employeesNotStarted->count()
                + $missingEndCode->count()
                + $trackingInterrupted->count()
                + $openClaims->count()
                + $unpaidInvoices->count()
                + $pendingBookings->count(),
        ]);
    }
}
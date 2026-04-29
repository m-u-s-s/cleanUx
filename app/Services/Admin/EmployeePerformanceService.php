<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Models\Mission;
use App\Models\Feedback;

class EmployeePerformanceService
{
    public function get(): array
    {
        $employees = User::where('role', 'employe')->get();

        return $employees->map(function ($employee) {
            $missions = Mission::where('lead_employee_id', $employee->id)->get();

            $completed = $missions->where('status', 'completed')->count();

            $rating = Feedback::whereHas('rendezVous', function ($q) use ($employee) {
                $q->where('employe_id', $employee->id);
            })->avg('note');

            $late = $missions->filter(function ($m) {
                return $m->planned_start_at
                    && $m->actual_start_at
                    && $m->actual_start_at->gt($m->planned_start_at->addMinutes(10));
            })->count();

            $margin = $missions->sum('margin');

            return [
                'id' => $employee->id,
                'name' => $employee->name,
                'missions' => $missions->count(),
                'completed' => $completed,
                'rating' => round($rating ?? 0, 1),
                'late' => $late,
                'margin' => round($margin, 2),
            ];
        })->sortByDesc('rating')->values()->all();
    }
}

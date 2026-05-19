<?php

namespace App\Services\Disputes;

use App\Models\ComplaintCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

class DisputeSlaService
{
    /**
     * Calcule l'échéance SLA d'une dispute en fonction de sa priorité + sévérité.
     */
    public function computeDueAt(string $priority, string $severity = ComplaintCase::SEVERITY_MEDIUM, ?Carbon $from = null): Carbon
    {
        $from ??= now();
        $hours = $this->hoursFor($priority, $severity);
        return $from->copy()->addHours($hours);
    }

    public function hoursFor(string $priority, string $severity = ComplaintCase::SEVERITY_MEDIUM): int
    {
        $base = (int) Config::get("disputes.sla_hours.{$priority}", 24);

        // Sévérité critique = 50% du SLA standard, low = 200%
        return match ($severity) {
            ComplaintCase::SEVERITY_CRITICAL => max(1, (int) round($base * 0.5)),
            ComplaintCase::SEVERITY_HIGH => max(1, (int) round($base * 0.75)),
            ComplaintCase::SEVERITY_LOW => $base * 2,
            default => $base,
        };
    }

    public function slaPolicyLabel(string $priority, string $severity): string
    {
        return $this->hoursFor($priority, $severity) . 'h';
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int,ComplaintCase>
     */
    public function findOverdueForEscalation()
    {
        return ComplaintCase::query()
            ->overdue()
            ->where('escalation_level', '<', (int) Config::get('disputes.max_escalation_level', 3))
            ->where(function ($q) {
                $q->whereNull('escalated_at')
                    ->orWhere('escalated_at', '<', now()->subHours($this->minEscalationGapHours()));
            })
            ->get();
    }

    protected function minEscalationGapHours(): int
    {
        $hours = Config::get('disputes.escalation_hours', [1 => 24, 2 => 48]);
        return is_array($hours) && count($hours) > 0 ? (int) min($hours) : 24;
    }
}

<?php

namespace App\Services\Assistant\Stats;

use App\Models\EnterpriseBookingApproval;
use App\Models\FinanceInvoice;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Phase 5.1 — Calcule les vraies statistiques contextuelles pour le chatbot.
 *
 * Remplace les placeholders zéro-valeur de AssistantContextBuilder.
 * Cache 60s pour éviter de recalculer à chaque message.
 *
 * Usage :
 *   $stats = app(AssistantStats::class)->forUser($user);
 *   $activeMissions = $stats['active_missions'];
 */
class AssistantStats
{
    private const CACHE_TTL = 60; // secondes

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        return Cache::remember(
            $this->cacheKey($user),
            self::CACHE_TTL,
            fn () => $this->compute($user)
        );
    }

    public function flush(User $user): void
    {
        Cache::forget($this->cacheKey($user));
    }

    private function cacheKey(User $user): string
    {
        return "assistant:stats:user:{$user->id}";
    }

    private function compute(User $user): array
    {
        $orgId = $user->organization_account_id;

        return [
            // CLIENT_COMPANY
            'active_missions'   => $this->activeMissions($user, $orgId),
            'pending_approvals' => $this->pendingApprovals($orgId),
            'unpaid_invoices'   => $this->unpaidInvoices($user, $orgId),

            // PROVIDER_INDEPENDENT
            'avg_rating' => $this->avgRating($user),

            // PROVIDER_COMPANY
            'team_name'       => $this->teamName($user),
            'pending_tasks'   => $this->pendingTasks($user),

            // ADMIN
            'admin_total_active_missions' => $this->totalActiveMissions(),
            'admin_monthly_revenue'       => $this->monthlyRevenue(),
            'admin_alerts'                => $this->alerts(),
        ];
    }

    // ──────────────────────────────────────────────────────
    // Stats individuelles
    // ──────────────────────────────────────────────────────

    private function activeMissions(User $user, ?int $orgId): int
    {
        $query = Mission::query()
            ->whereIn('status', ['en_route', 'sur_place', 'in_progress', 'on_route', 'on_site', 'confirme', 'confirmed', 'en_attente', 'pending']);

        if ($orgId) {
            $query->where('organization_account_id', $orgId);
        } else {
            // Cas user lambda : missions où il est customer
            $query->whereHas('rendezVous', fn ($q) => $q->where('customer_user_id', $user->id));
        }

        return $query->count();
    }

    private function pendingApprovals(?int $orgId): int
    {
        if (! $orgId) {
            return 0;
        }

        return EnterpriseBookingApproval::query()
            ->where('organization_account_id', $orgId)
            ->where('status', 'pending')
            ->count();
    }

    private function unpaidInvoices(User $user, ?int $orgId): int
    {
        $query = FinanceInvoice::query()
            ->whereNull('paid_at')
            ->whereIn('status', ['issued', 'overdue', 'pending', 'unpaid', 'sent']);

        if ($orgId) {
            $query->where('organization_account_id', $orgId);
        } else {
            $query->where('client_id', $user->id);
        }

        return $query->count();
    }

    private function avgRating(User $user): ?float
    {
        // Si tu as une table mission_quality_reviews ou similar, requêter ici.
        // Pour l'instant, on retourne null pour signifier "pas calculé".
        try {
            $reviews = \App\Models\MissionQualityReview::query()
                ->whereHas('mission.assignments', fn ($q) => $q->where('user_id', $user->id))
                ->whereNotNull('overall_rating')
                ->avg('overall_rating');
            return $reviews ? round((float) $reviews, 2) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function teamName(User $user): ?string
    {
        try {
            $member = \App\Models\FieldTeamMember::query()
                ->with('team')
                ->where('user_id', $user->id)
                ->first();
            return $member?->team?->name;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function pendingTasks(User $user): int
    {
        try {
            return MissionAssignment::query()
                ->where('user_id', $user->id)
                ->whereIn('status', ['pending', 'assigned', 'accepted'])
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function totalActiveMissions(): int
    {
        return Mission::query()
            ->whereIn('status', ['en_route', 'sur_place', 'confirme', 'in_progress', 'on_route', 'on_site', 'confirmed'])
            ->count();
    }

    private function monthlyRevenue(): float
    {
        return (float) FinanceInvoice::query()
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', now()->startOfMonth())
            ->sum('total_amount');
    }

    private function alerts(): int
    {
        // Critères d'alerte à customiser selon ta logique métier.
        // Ici : nombre d'incidents critiques ouverts.
        try {
            return \App\Models\MissionIncident::query()
                ->where('status', 'open')
                ->where('severity', 'critical')
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}

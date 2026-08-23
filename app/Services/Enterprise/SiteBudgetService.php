<?php

namespace App\Services\Enterprise;

use App\Models\Booking;
use App\Models\OrganizationSiteBudget;
use App\Services\Organizations\OrganizationNotifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** LES BUDGETS PAR LOCAL (E7) — ce qui a été engagé, contre ce qui était prévu. */
class SiteBudgetService
{
    public function __construct(
        protected OrganizationNotifier $notifier,
    ) {}

    /**
     * Où en est un budget.
     *
     * @return array<string, mixed>
     */
    public function etat(OrganizationSiteBudget $budget): array
    {
        $engage = $this->engageSurLaPeriode($budget);
        $plafond = max(1, (int) $budget->limit_cents);
        $pourcentage = (int) round($engage / $plafond * 100);

        return [
            'budget_id' => $budget->id,
            'site_id' => $budget->organization_site_id,
            // `null` = toute la société : c'est le premier budget que la plupart posent.
            'site_name' => $budget->site?->name,
            'period' => $budget->period,
            'period_start' => $budget->period_start->toDateString(),
            'period_end' => $budget->finDePeriode()->toDateString(),
            'limit_cents' => (int) $budget->limit_cents,
            'committed_cents' => $engage,
            'remaining_cents' => (int) $budget->limit_cents - $engage,
            'usage_percent' => $pourcentage,
            'alert_threshold_percent' => (int) $budget->alert_threshold_percent,
            // Deux états distincts : « il faut arbitrer » et « c'est dépassé ». Les confondre
            // ferait manquer la fenêtre où arbitrer sert encore à quelque chose.
            'is_warning' => $pourcentage >= (int) $budget->alert_threshold_percent && $pourcentage < 100,
            'is_exceeded' => $pourcentage >= 100,
        ];
    }

    /**
     * L'état de tous les budgets EN COURS d'une société.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function etatsEnCours(int $organisationId, ?Carbon $moment = null): Collection
    {
        $moment ??= Carbon::now();

        return OrganizationSiteBudget::query()
            ->where('organization_account_id', $organisationId)
            ->with('site:id,name')
            ->get()
            ->filter(fn (OrganizationSiteBudget $budget) => $budget->couvre($moment))
            ->map(fn (OrganizationSiteBudget $budget) => $this->etat($budget))
            ->sortByDesc('usage_percent')
            ->values();
    }

    /** Vérifier les budgets touchés par une réservation, et alerter si un palier vient d'être franchi. */
    public function verifierApresReservation(Booking $booking): void
    {
        $organisationId = (int) ($booking->customer_organization_id ?? 0);

        if ($organisationId <= 0) {
            return;
        }

        // `Carbon::instance()` PARCE QUE LES DEUX CARBON NE SONT PAS LE MÊME TYPE.
        $moment = Carbon::instance($booking->scheduled_at ?? $booking->created_at ?? now());

        $budgets = OrganizationSiteBudget::query()
            ->where('organization_account_id', $organisationId)
            ->where(function ($q) use ($booking) {
                // Le budget du LOCAL, et celui de toute la société : une réservation consomme les
                // deux, et n'en signaler qu'un laisserait l'autre dériver en silence.
                $q->whereNull('organization_site_id')
                    ->orWhere('organization_site_id', $booking->organization_site_id);
            })
            ->with('site:id,name')
            ->get()
            ->filter(fn (OrganizationSiteBudget $budget) => $budget->couvre($moment));

        foreach ($budgets as $budget) {
            $this->alerterSiFranchissement($budget);
        }
    }

    /** Ce qui a été ENGAGÉ sur la période — pas ce qui a été facturé. */
    public function engageSurLaPeriode(OrganizationSiteBudget $budget): int
    {
        return (int) round(Booking::query()
            ->where('customer_organization_id', $budget->organization_account_id)
            ->when(
                $budget->organization_site_id !== null,
                fn ($q) => $q->where('organization_site_id', $budget->organization_site_id),
            )
            // Une annulée n'engage plus rien.
            ->whereNotIn('status', ['annule', 'cancelled', 'refused', 'refuse'])
            ->whereBetween('scheduled_at', [
                $budget->period_start->copy()->startOfDay(),
                $budget->finDePeriode(),
            ])
            ->sum('devis_estime') * 100);
    }

    /** Prévenir, une seule fois par palier. */
    protected function alerterSiFranchissement(OrganizationSiteBudget $budget): void
    {
        $etat = $this->etat($budget);
        $palier = $etat['is_exceeded'] ? 100 : ($etat['is_warning'] ? (int) $budget->alert_threshold_percent : null);

        if ($palier === null || (int) ($budget->alerted_at_percent ?? 0) >= $palier) {
            return;
        }

        try {
            // PRÉVENIR CEUX QUI PEUVENT ARBITRER.
            $this->notifier->notifierPorteursDe(
                organisationId: (int) $budget->organization_account_id,
                permission: 'finance.view',
                titre: $etat['is_exceeded']
                    ? 'Budget dépassé : '.($etat['site_name'] ?? 'toute la société')
                    : 'Budget bientôt atteint : '.($etat['site_name'] ?? 'toute la société'),
                corps: sprintf(
                    '%s engagés sur %s (%d %%), période du %s au %s.',
                    number_format($etat['committed_cents'] / 100, 2, ',', ' ').' €',
                    number_format($etat['limit_cents'] / 100, 2, ',', ' ').' €',
                    $etat['usage_percent'],
                    $etat['period_start'],
                    $etat['period_end'],
                ),
                donnees: ['organization_site_budget_id' => $budget->id],
                cleIdempotence: 'budget:'.$budget->id.':'.$palier,
            );
        } catch (\Throwable $e) {
            // Une alerte qui échoue ne doit pas empêcher d'enregistrer qu'on a franchi le palier :
            // sinon elle repartirait à chaque réservation suivante.
            report($e);
        }

        $budget->forceFill([
            'alerted_at' => now(),
            'alerted_at_percent' => $palier,
        ])->save();
    }
}

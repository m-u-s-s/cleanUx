<?php

namespace App\Services\Workforce;

use App\Models\InventoryMovement;
use App\Models\Mission;
use App\Models\TimeEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** LA RENTABILITÉ D'UNE MISSION, D'UN SITE, D'UNE ÉQUIPE (E22). */
class ProfitabilityService
{
    /** Le coût horaire retenu quand la société n'en a pas déclaré. */
    public const DEFAULT_HOURLY_COST_CENTS = 2200;

    /**
     * La rentabilité d'une mission.
     *
     * @return array<string, mixed>
     */
    public function pourLaMission(Mission $mission): array
    {
        $produitCents = (int) round(((float) ($mission->booking->devis_estime ?? 0)) * 100);

        $minutes = (int) TimeEntry::query()
            ->where('mission_id', $mission->id)
            ->whereIn('status', [TimeEntry::STATUS_RECORDED, TimeEntry::STATUS_APPROVED])
            ->sum('worked_minutes');

        $tauxCents = $this->tauxHoraireCents($mission);
        $coutMainDoeuvre = (int) round($minutes / 60 * $tauxCents);
        $coutConsommables = $this->coutDesConsommables($mission);

        $coutTotal = $coutMainDoeuvre + $coutConsommables;

        return [
            'mission_id' => $mission->id,
            'revenue_cents' => $produitCents,
            'worked_minutes' => $minutes,
            'labour_cost_cents' => $coutMainDoeuvre,
            'consumables_cost_cents' => $coutConsommables,
            'total_cost_cents' => $coutTotal,
            'margin_cents' => $produitCents - $coutTotal,
            // Le taux est une hypothèse : l'écran doit pouvoir le dire plutôt que de présenter la
            // marge comme un fait établi.
            'hourly_rate_cents' => $tauxCents,
            'hourly_rate_source' => $this->sourceDuTaux($mission),
            // Une mission sans pointage n'a pas de marge calculable : le signaler vaut mieux que
            // d'afficher 100 %.
            'has_timesheet' => $minutes > 0,
        ];
    }

    /**
     * L'agrégat d'une période pour une société, ventilé par la clé demandée.
     *
     * @param  'site'|'team'|'agency'  $par
     * @return Collection<int|string, array{key: mixed, missions_count: int<0, max>, missions_without_timesheet: int<0, max>, revenue_cents: int, total_cost_cents: int, margin_cents: int, worked_minutes: int}>
     */
    public function pourLaPeriode(
        int $organisationId,
        Carbon $debut,
        Carbon $fin,
        string $par = 'site',
    ): Collection {
        $missions = Mission::query()
            ->where('provider_organization_id', $organisationId)
            ->whereBetween('created_at', [$debut, $fin])
            ->with('booking')
            ->get();

        return $missions
            ->groupBy(fn (Mission $mission) => match ($par) {
                'team' => $mission->provider_team_id ?? $mission->field_team_id ?? 0,
                'agency' => $mission->provider_agency_id ?? 0,
                default => $mission->organization_site_id ?? 0,
            })
            ->map(function (Collection $groupe, $cle) {
                $lignes = $groupe->map(fn (Mission $mission) => $this->pourLaMission($mission));

                return [
                    'key' => $cle,
                    'missions_count' => $lignes->count(),
                    // Les missions sans pointage sont comptées À PART : les fondre dans la moyenne
                    // ferait apparaître une marge de 100 % sur chacune d'elles.
                    'missions_without_timesheet' => $lignes->where('has_timesheet', false)->count(),
                    'revenue_cents' => (int) $lignes->sum('revenue_cents'),
                    'total_cost_cents' => (int) $lignes->sum('total_cost_cents'),
                    'margin_cents' => (int) $lignes->sum('margin_cents'),
                    'worked_minutes' => (int) $lignes->sum('worked_minutes'),
                ];
            })
            ->values()
            ->keyBy('key');
    }

    /** Ce que les consommables déclarés sur cette mission ont coûté. */
    protected function coutDesConsommables(Mission $mission): int
    {
        return (int) InventoryMovement::query()
            ->where('mission_id', $mission->id)
            ->where('type', InventoryMovement::TYPE_CONSUMPTION)
            ->with('item:id,unit_cost_cents')
            ->get()
            ->sum(fn (InventoryMovement $mouvement) => abs((int) $mouvement->quantity)
                * (int) ($mouvement->item->unit_cost_cents ?? 0));
    }

    protected function tauxHoraireCents(Mission $mission): int
    {
        $declare = data_get(
            $mission->providerOrganization?->metadata,
            'workforce.hourly_cost_cents',
        );

        return is_numeric($declare) && (int) $declare > 0
            ? (int) $declare
            : self::DEFAULT_HOURLY_COST_CENTS;
    }

    protected function sourceDuTaux(Mission $mission): string
    {
        $declare = data_get(
            $mission->providerOrganization?->metadata,
            'workforce.hourly_cost_cents',
        );

        return is_numeric($declare) && (int) $declare > 0 ? 'declared' : 'default';
    }
}

<?php

namespace App\Livewire\ClientCompany\Analytics;

use App\Services\Analytics\AnalyticsKpiService;
use App\Services\Analytics\DateRangeResolver;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Phase 7 — Dashboard analytics pour client entreprise.
 *
 * Affiche :
 *   - KPI cards (CA, RDV, terminés, taux annulation, satisfaction, sites actifs)
 *   - Graphique CA mensuel (12 derniers mois)
 *   - Graphique répartition par statut
 *   - Top 10 services / sites
 *   - Évolution satisfaction
 *   - Alertes business
 *   - Boutons exports CSV
 *
 * Sélecteur de période avec presets + custom dates.
 * State persisté dans l'URL pour partage de vues filtrées.
 */
class ClientAnalyticsDashboard extends Component
{
    #[Url(as: 'period', keep: true)]
    public string $preset = 'last_30d';

    #[Url(as: 'from')]
    public ?string $customFrom = null;

    #[Url(as: 'to')]
    public ?string $customTo = null;

    public function setPreset(string $preset): void
    {
        if (! in_array($preset, DateRangeResolver::PRESETS, true)) {
            return;
        }
        $this->preset = $preset;

        // Si on quitte le mode custom, vider les dates
        if ($preset !== 'custom') {
            $this->customFrom = null;
            $this->customTo = null;
        }
    }

    public function applyCustomDates(): void
    {
        $this->preset = 'custom';
    }

    public function render(): View
    {
        $user = Auth::user();
        $orgId = $user->organization_account_id;

        $resolver = app(DateRangeResolver::class);
        [$from, $to, $periodLabel] = $resolver->resolve($this->preset, $this->customFrom, $this->customTo);

        $kpis = app(AnalyticsKpiService::class);

        return view('livewire.client-company.analytics.client-analytics-dashboard', [
            'mainKpis'          => $kpis->mainKpis($orgId, $from, $to),
            'monthlyRevenue'    => $kpis->monthlyRevenue($orgId, 12),
            'statusBreakdown'   => $kpis->statusBreakdown($orgId, $from, $to),
            'topServices'       => $kpis->topServices($orgId, $from, $to, 10),
            'topSites'          => $kpis->topSites($orgId, $from, $to, 10),
            'satisfactionTrend' => $kpis->satisfactionTrend($orgId, 12),
            'alerts'            => $kpis->alerts($orgId),
            'periodLabel'       => $periodLabel,
            'periodFrom'        => $from->toDateString(),
            'periodTo'          => $to->toDateString(),
            'presetOptions'     => $resolver->presetOptions(),
            'isCompany'         => (bool) $orgId,
        ]);
    }
}

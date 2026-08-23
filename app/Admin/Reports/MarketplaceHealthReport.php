<?php

namespace App\Admin\Reports;

use App\Admin\Console\AdminReport;
use App\Admin\Console\ReportTile;
use App\Services\Admin\DemandForecastService;
use App\Services\Admin\MarketplaceHealthService;
use App\Services\Admin\SurgeOverviewService;
use Illuminate\Support\Carbon;

/** LA SANTÉ DU MARCHÉ, VUE DE LA CONSOLE MOBILE (E29, E30, E28). */
class MarketplaceHealthReport implements AdminReport
{
    public function key(): string
    {
        return 'marketplace-health';
    }

    public function sections(): array
    {
        return [
            [
                'title' => 'Offre et demande (30 jours)',
                'tiles' => [
                    ReportTile::make(
                        'recherches',
                        'Recherches',
                        fn () => (int) ($this->sante()['searches_count'] ?? 0),
                    ),
                    ReportTile::make(
                        'sans_candidat_pct',
                        'Sans candidat (%)',
                        fn () => (int) round((float) ($this->sante()['no_candidate_rate'] ?? 0)),
                        // Au-delà d'un dixième, le marché ne tient plus dans au moins une zone.
                        tone: fn ($v) => $v >= 10 ? ReportTile::TONE_DANGER : ReportTile::TONE_SUCCESS,
                    ),
                    ReportTile::make(
                        'zones_a_risque',
                        'Zones à risque',
                        fn () => count($this->sante()['zones_at_risk'] ?? []),
                        // SA VALEUR NORMALE EST ZÉRO.
                        tone: fn ($v) => $v > 0 ? ReportTile::TONE_DANGER : ReportTile::TONE_SUCCESS,
                    ),
                    ReportTile::make(
                        'zones_sans_demande',
                        'Zones sans demande',
                        fn () => (int) ($this->sante()['zones_without_data'] ?? 0),
                        // Ni bon ni mauvais en soi : une zone ouverte qui n'a jamais rien vendu se
                        // regarde, elle ne s'alarme pas.
                        tone: fn () => ReportTile::TONE_NEUTRAL,
                    ),
                ],
            ],
            [
                'title' => 'Semaine à venir',
                'tiles' => [
                    ReportTile::make(
                        'projection_totale',
                        'Interventions projetées',
                        fn () => (int) collect(app(DemandForecastService::class)->projection())
                            ->where('has_enough_history', true)
                            ->sum('next_week_forecast'),
                    ),
                    ReportTile::make(
                        'couples_projetables',
                        'Couples zone × métier projetables',
                        // ON NE PROJETTE PAS SOUS QUATRE SEMAINES d'observation : cette tuile dit sur combien de couples la projection vaut quelque chose, ce qui empêche de lire le total ci-dessus comme une prévision complète.
                        fn () => collect(app(DemandForecastService::class)->projection())
                            ->where('has_enough_history', true)
                            ->count(),
                        tone: fn () => ReportTile::TONE_NEUTRAL,
                    ),
                ],
            ],
            [
                'title' => 'Majorations',
                'tiles' => [
                    ReportTile::make(
                        'grilles_majorees',
                        'Grilles majorées',
                        fn () => (int) (app(SurgeOverviewService::class)->carte()['surged_count'] ?? 0),
                        tone: fn () => ReportTile::TONE_NEUTRAL,
                    ),
                    ReportTile::make(
                        'au_dessus_du_plafond',
                        'Au-dessus du plafond',
                        fn () => (int) (app(SurgeOverviewService::class)->carte()['exceeding_cap_count'] ?? 0),
                        // UNE VALEUR NON NULLE SIGNIFIE QUE L'ÉCRAN MENT quelque part : la grille affiche 3,50 et le moteur applique 3,00.
                        tone: fn ($v) => $v > 0 ? ReportTile::TONE_DANGER : ReportTile::TONE_SUCCESS,
                    ),
                ],
            ],
        ];
    }

    /**
     * Le résumé, calculé UNE FOIS pour toutes les tuiles de la première section.
     *
     * @return array<string, mixed>
     */
    protected function sante(): array
    {
        return $this->resume ??= app(MarketplaceHealthService::class)->resume(
            Carbon::now()->subDays(30),
            Carbon::now(),
        );
    }

    /** @var array<string, mixed>|null */
    protected ?array $resume = null;
}

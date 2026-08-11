<?php

namespace App\Admin\Reports;

use App\Admin\Console\AdminReport;
use App\Admin\Console\ReportTile;
use App\Services\Admin\DemandForecastService;
use App\Services\Admin\MarketplaceHealthService;
use App\Services\Admin\SurgeOverviewService;
use Illuminate\Support\Carbon;

/**
 * LA SANTÉ DU MARCHÉ, VUE DE LA CONSOLE MOBILE (E29, E30, E28).
 *
 * UN RAPPORT ET NON UNE LISTE, parce que la question n'est pas « quelles lignes existent » mais
 * « est-ce que le marché tient ». Aucune table ne porte cette réponse : elle se lit en croisant les
 * recherches épuisées, la couverture par zone et les majorations en vigueur.
 *
 * `zones_a_risque` EST LA TUILE QUI COMMANDE UNE ACTION. Sa valeur normale est zéro : toute valeur
 * non nulle désigne une zone où une recherche sur cinq s'épuise sans candidat — c'est-à-dire des
 * clients qu'on perd, et un recrutement à lancer. Le ton bascule dès un, parce que c'est un
 * compteur d'anomalie et non un indicateur d'activité.
 *
 * LES TROIS GESTES DE RATTRAPAGE NE SONT PAS ICI, et c'est délibéré. Relancer une recherche,
 * contacter un client, offrir un geste : ces décisions se prennent en regardant le tableau complet,
 * sur l'écran web. Les porter au mobile supposerait de répondre à « on relance quoi » sans le
 * contexte qui précède.
 *
 * CHAQUE TUILE SE CALCULE SEULE. Le contrat de `ReportTile` rattrape les erreurs, si bien qu'une
 * table absente coûte une tuile plutôt que l'écran — mais une requête qui rendrait zéro en silence
 * ferait croire à une plateforme en parfaite santé. C'est pourquoi elles lisent les services, qui
 * eux sont testés.
 */
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
                        /*
                         * SA VALEUR NORMALE EST ZÉRO. Toute valeur non nulle désigne une zone où
                         * une recherche sur cinq s'épuise sans candidat : des clients qu'on perd,
                         * et un recrutement à lancer.
                         */
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
                        /*
                         * ON NE PROJETTE PAS SOUS QUATRE SEMAINES d'observation : cette tuile dit
                         * sur combien de couples la projection vaut quelque chose, ce qui empêche
                         * de lire le total ci-dessus comme une prévision complète.
                         */
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
                        /*
                         * UNE VALEUR NON NULLE SIGNIFIE QUE L'ÉCRAN MENT quelque part : la grille
                         * affiche 3,50 et le moteur applique 3,00. Ce n'est pas une majoration
                         * excessive, c'est un écart inexplicable au client.
                         */
                        tone: fn ($v) => $v > 0 ? ReportTile::TONE_DANGER : ReportTile::TONE_SUCCESS,
                    ),
                ],
            ],
        ];
    }

    /**
     * Le résumé, calculé UNE FOIS pour toutes les tuiles de la première section.
     *
     * Sans ce cache d'instance, chaque tuile relancerait le balayage complet des recherches : le
     * rapport ferait quatre fois le même travail pour afficher quatre nombres du même calcul.
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

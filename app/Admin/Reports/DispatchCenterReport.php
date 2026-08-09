<?php

namespace App\Admin\Reports;

use App\Admin\Console\AdminReport;
use App\Admin\Console\ReportTile;
use App\Models\AsapDispatchRequest;
use App\Models\MissionAssignment;
use App\Support\Domain\AsapStatus;

/**
 * LA RÉPARTITION, VUE DE LA CONSOLE MOBILE — les mêmes chiffres que l'écran web.
 *
 * Ce n'est pas une liste de lignes d'une table, et c'est pourquoi ce module est un RAPPORT : ce
 * qu'un exploitant veut savoir — « combien de clients ont attendu pour rien » — se lit en croisant
 * les recherches et les offres, pas en parcourant une table.
 *
 * `sans_candidat` est la tuile qui compte. Sa valeur normale est zéro : toute valeur non nulle
 * désigne des clients qui ont attendu devant leur porte sans que personne ne vienne. Le ton bascule
 * dès un — c'est un compteur d'anomalie, pas un indicateur d'activité.
 *
 * REFUS ET SILENCES SONT SÉPARÉS, et ce n'est pas cosmétique : un refus dit qu'on a proposé la
 * mauvaise course à la bonne personne (rayon, prix, métier), un silence dit que la notification
 * n'est pas arrivée ou que la fenêtre est trop courte. Les additionner rendrait les deux invisibles.
 */
class DispatchCenterReport implements AdminReport
{
    public function key(): string
    {
        return 'dispatch-center';
    }

    public function sections(): array
    {
        return [
            [
                'title' => 'Recherches',
                'tiles' => [
                    ReportTile::make(
                        'en_recherche',
                        'En recherche',
                        fn () => AsapDispatchRequest::query()->where('status', AsapStatus::SEARCHING)->count(),
                    ),
                    ReportTile::make(
                        'sans_candidat',
                        'Sans candidat',
                        fn () => AsapDispatchRequest::query()->where('status', AsapStatus::EXPIRED)->count(),
                        tone: fn ($v) => $v > 0 ? ReportTile::TONE_DANGER : ReportTile::TONE_SUCCESS,
                    ),
                    ReportTile::make(
                        'acceptees',
                        'Acceptées',
                        fn () => AsapDispatchRequest::query()->where('status', AsapStatus::ACCEPTED)->count(),
                        tone: fn () => ReportTile::TONE_SUCCESS,
                    ),
                ],
            ],
            [
                'title' => 'Offres (24 h)',
                'tiles' => [
                    ReportTile::make(
                        'offres_24h',
                        'Offres émises',
                        fn () => MissionAssignment::query()->where('created_at', '>=', now()->subDay())->count(),
                    ),
                    ReportTile::make(
                        'refus_24h',
                        'Refus',
                        fn () => MissionAssignment::query()
                            ->where('assignment_status', 'declined')
                            ->where('updated_at', '>=', now()->subDay())
                            ->count(),
                        tone: fn ($v) => $v > 0 ? ReportTile::TONE_WARNING : ReportTile::TONE_SUCCESS,
                    ),
                    ReportTile::make(
                        'silences_24h',
                        'Sans réponse',
                        fn () => MissionAssignment::query()
                            ->where('assignment_status', 'expired')
                            ->where('updated_at', '>=', now()->subDay())
                            ->count(),
                        tone: fn ($v) => $v > 0 ? ReportTile::TONE_WARNING : ReportTile::TONE_SUCCESS,
                    ),
                    ReportTile::make(
                        'acceptations_24h',
                        'Acceptations',
                        fn () => MissionAssignment::query()
                            ->where('assignment_status', 'accepted')
                            ->where('updated_at', '>=', now()->subDay())
                            ->count(),
                        tone: fn () => ReportTile::TONE_SUCCESS,
                    ),
                ],
            ],
        ];
    }
}

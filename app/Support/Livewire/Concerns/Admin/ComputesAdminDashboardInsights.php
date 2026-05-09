<?php

namespace App\Support\Livewire\Concerns\Admin;

use App\Models\LimiteJournaliere;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

trait ComputesAdminDashboardInsights
{
        public function getUrgencesProperty()
        {
            return $this->scopedRendezVousQuery(false)
                ->with(['client', 'employe', 'serviceZone'])
                ->where('priorite', 'urgente')
                ->whereIn('status', ['en_attente', 'confirme', 'en_route', 'sur_place'])
                ->orderBy('date')
                ->orderBy('heure')
                ->limit(5)
                ->get();
        }

        public function getInterventionsDuJourProperty()
        {
            return $this->scopedRendezVousQuery(false)
                ->with(['client', 'employe', 'serviceZone'])
                ->whereDate('date', today())
                ->orderBy('heure')
                ->limit(8)
                ->get();
        }

        public function getChargeEmployesProperty()
        {
            return $this->scopedEmployeesQuery()
                ->get()
                ->map(function ($employe) {
                    $rdvsJour = $this->scopedRendezVousQuery(false)
                        ->where('employe_id', $employe->id)
                        ->whereDate('date', today())
                        ->whereIn('status', ['confirme', 'en_attente', 'en_route', 'sur_place'])
                        ->get();

                    $totalMinutes = $rdvsJour->sum(function ($rdv) {
                        $duration = $rdv->duree ?? $rdv->duree_estimee ?? 90;
                        return $duration + 30;
                    });

                    return [
                        'employe' => $employe,
                        'count' => $rdvsJour->count(),
                        'minutes' => $totalMinutes,
                        'hours' => round($totalMinutes / 60, 1),
                    ];
                })
                ->sortByDesc('minutes')
                ->values();
        }

        public function getMissionsTermineesProperty()
        {
            return $this->scopedRendezVousQuery(false)
                ->with(['client', 'employe', 'serviceZone'])
                ->where('status', 'termine')
                ->orderByDesc('mission_finished_at')
                ->orderByDesc('date')
                ->limit(6)
                ->get();
        }

        public function getQualiteMissionsProperty()
        {
            return $this->scopedRendezVousQuery(false)
                ->with(['client', 'employe', 'serviceZone'])
                ->where('status', 'termine')
                ->orderByDesc('mission_finished_at')
                ->limit(12)
                ->get()
                ->map(function ($rdv) {
                    $estimated = $rdv->duree_estimee ?? $rdv->duree ?? null;
                    $real = $rdv->duree_reelle;

                    $difference = null;
                    if (! is_null($estimated) && ! is_null($real)) {
                        $difference = $real - $estimated;
                    }

                    return [
                        'rdv' => $rdv,
                        'has_report' => filled($rdv->commentaire_fin_mission),
                        'has_after_photos' => ! empty($rdv->photos_apres),
                        'estimated' => $estimated,
                        'real' => $real,
                        'difference' => $difference,
                        'is_long_overrun' => ! is_null($difference) && $difference >= 30,
                        'is_short_underrun' => ! is_null($difference) && $difference <= -30,
                    ];
                });
        }

        public function getQualiteStatsProperty()
        {
            $missions = $this->scopedRendezVousQuery(false)
                ->where('status', 'termine')
                ->get();

            return [
                'sans_rapport' => $missions->filter(fn ($rdv) => blank($rdv->commentaire_fin_mission))->count(),
                'sans_photos_apres' => $missions->filter(fn ($rdv) => empty($rdv->photos_apres))->count(),
                'avec_duree_reelle' => $missions->filter(fn ($rdv) => ! is_null($rdv->duree_reelle))->count(),
            ];
        }

        public function getRecentActivityLogsProperty()
        {
            return $this->scopedActivityLogsQuery()
                ->limit(10)
                ->get();
        }

        public function getTopServicesProperty()
        {
            return Cache::remember($this->cacheKey('topServices'), now()->addMinutes(10), function () {
                return $this->scopedRendezVousQuery(false)
                    ->with('serviceCatalog:id,name')
                    ->get(['id', 'service_catalog_id'])
                    ->groupBy(fn (Booking $rdv) => $rdv->service_display_name)
                    ->map(fn ($items, $label) => (object) [
                        'label' => $label,
                        'total' => $items->count(),
                    ])
                    ->sortByDesc('total')
                    ->take(5)
                    ->values();
            });
        }

        public function getTopVillesProperty()
        {
            return Cache::remember($this->cacheKey('topVilles'), now()->addMinutes(10), function () {
                return $this->scopedRendezVousQuery(false)
                    ->selectRaw('ville, COUNT(*) as total')
                    ->whereNotNull('ville')
                    ->where('ville', '!=', '')
                    ->groupBy('ville')
                    ->orderByDesc('total')
                    ->limit(5)
                    ->get();
            });
        }

        public function getDureeStatsProperty()
        {
            return Cache::remember($this->cacheKey('dureeStats'), now()->addMinutes(10), function () {
                $missions = $this->scopedRendezVousQuery(false)
                    ->where('status', 'termine')
                    ->whereNotNull('duree_estimee')
                    ->whereNotNull('duree_reelle')
                    ->get();

                if ($missions->isEmpty()) {
                    return [
                        'avg_estimated' => null,
                        'avg_real' => null,
                        'avg_gap' => null,
                    ];
                }

                return [
                    'avg_estimated' => round($missions->avg('duree_estimee')),
                    'avg_real' => round($missions->avg('duree_reelle')),
                    'avg_gap' => round($missions->avg(fn ($rdv) => $rdv->duree_reelle - $rdv->duree_estimee)),
                ];
            });
        }

        public function getPerformanceEmployesProperty()
        {
            return Cache::remember($this->cacheKey('performanceEmployes'), now()->addMinutes(10), function () {
                return $this->scopedEmployeesQuery()
                    ->get()
                    ->map(function ($employe) {
                        $missions = $this->scopedRendezVousQuery(false)
                            ->where('employe_id', $employe->id)
                            ->where('status', 'termine')
                            ->with('feedback')
                            ->get();

                        $avgGap = null;
                        $withDurations = $missions->filter(
                            fn ($rdv) => ! is_null($rdv->duree_estimee) && ! is_null($rdv->duree_reelle)
                        );

                        if ($withDurations->isNotEmpty()) {
                            $avgGap = round($withDurations->avg(fn ($rdv) => $rdv->duree_reelle - $rdv->duree_estimee));
                        }

                        $feedbacks = $missions->filter(fn ($rdv) => $rdv->feedback)->pluck('feedback');
                        $avgNote = $feedbacks->isNotEmpty() ? round($feedbacks->avg('note'), 1) : null;

                        return [
                            'employe' => $employe,
                            'missions_terminees' => $missions->count(),
                            'avg_gap' => $avgGap,
                            'avg_note' => $avgNote,
                        ];
                    })
                    ->sortByDesc('missions_terminees')
                    ->values()
                    ->take(6);
            });
        }

        public function getFeedbackRateProperty()
        {
            return Cache::remember($this->cacheKey('feedbackRate'), now()->addMinutes(10), function () {
                $terminees = $this->scopedRendezVousQuery(false)
                    ->where('status', 'termine')
                    ->count();

                if ($terminees === 0) {
                    return 0;
                }

                $avecFeedback = $this->scopedRendezVousQuery(false)
                    ->where('status', 'termine')
                    ->whereHas('feedback')
                    ->count();

                return round(($avecFeedback / $terminees) * 100);
            });
        }

        public function getUrgencesVieillissantesProperty()
        {
            return $this->scopedRendezVousQuery(false)
                ->with(['client', 'employe', 'serviceZone'])
                ->where('priorite', 'urgente')
                ->where('status', 'en_attente')
                ->where('created_at', '<=', now()->subHours(4))
                ->orderBy('created_at')
                ->limit(5)
                ->get();
        }

        public function getServicesSousEstimesProperty()
        {
            return Cache::remember($this->cacheKey('servicesSousEstimes'), now()->addMinutes(10), function () {
            return $this->scopedRendezVousQuery(false)
                ->with('serviceCatalog:id,name')
                ->where('status', 'termine')
                ->whereNotNull('duree_estimee')
                ->whereNotNull('duree_reelle')
                ->get(['id', 'service_catalog_id', 'duree_estimee', 'duree_reelle'])
                ->groupBy(fn (Booking $rdv) => $rdv->service_display_name)
                ->map(function ($items) {
                    return [
                        'avg_gap' => round($items->avg(fn ($rdv) => $rdv->duree_reelle - $rdv->duree_estimee)),
                        'count' => $items->count(),
                    ];
                })
                ->filter(fn ($row) => $row['count'] >= 3 && $row['avg_gap'] >= 20)
                ->sortByDesc('avg_gap');
            });
        }

        public function getAdminKpisProperty()
        {
            return Cache::remember($this->cacheKey('adminKpis'), now()->addMinutes(10), function () {
                $today = today();
                $baseQuery = $this->scopedRendezVousQuery(false);

                return [
                    'en_attente' => (clone $baseQuery)->where('status', 'en_attente')->count(),
                    'urgentes_vieilles' => $this->urgencesVieillissantes->count(),
                    'missions_longues' => $this->qualiteMissions->filter(fn ($item) => $item['is_long_overrun'])->count(),
                    'employes_surcharges' => $this->chargeEmployes->filter(fn ($item) => $item['minutes'] >= 480)->count(),
                    'missions_du_jour' => (clone $baseQuery)->whereDate('date', $today)->count(),
                    'missions_terminees_mois' => (clone $baseQuery)
                        ->where('status', 'termine')
                        ->whereMonth('date', now()->month)
                        ->count(),
                ];
            });
        }

        public function getRecommendationsProperty()
        {
            $recommendations = collect();

            $surcharges = $this->chargeEmployes->filter(fn ($item) => $item['minutes'] >= 480);
            foreach ($surcharges as $item) {
                $recommendations->push([
                    'level' => 'danger',
                    'title' => 'Employé surchargé',
                    'message' => $item['employe']->name . ' dépasse 8h planifiées aujourd’hui.',
                ]);
            }

            foreach ($this->servicesSousEstimes->take(3) as $service => $row) {
                $recommendations->push([
                    'level' => 'warning',
                    'title' => 'Service sous-estimé',
                    'message' => ucfirst(str_replace('_', ' ', $service)) . ' dépasse en moyenne l’estimé de ' . $row['avg_gap'] . ' min.',
                ]);
            }

            foreach ($this->topVilles->take(2) as $ville) {
                if ($ville->total >= 5) {
                    $recommendations->push([
                        'level' => 'info',
                        'title' => 'Zone à forte demande',
                        'message' => $ville->ville . ' concentre actuellement ' . $ville->total . ' demandes.',
                    ]);
                }
            }

            if ($this->feedbackRate < 40) {
                $recommendations->push([
                    'level' => 'warning',
                    'title' => 'Taux de feedback faible',
                    'message' => 'Le taux de feedback est de ' . $this->feedbackRate . '%. Envisage une relance client plus forte.',
                ]);
            }

            foreach ($this->urgencesVieillissantes as $rdv) {
                $recommendations->push([
                    'level' => 'danger',
                    'title' => 'Urgence trop longtemps en attente',
                    'message' => 'Mission urgente #' . $rdv->id . ' en attente depuis plus de 4h.',
                ]);
            }

            return $recommendations->take(8);
        }

}

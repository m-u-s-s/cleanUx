<?php

namespace App\Services\Admin;

use App\Models\Booking;
use App\Models\Feedback;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminAnalyticsService
{
    public function overview(): array
    {
        $driver = DB::connection()->getDriverName();

        $monthExpression = $driver === 'sqlite'
            ? "CAST(strftime('%m', date) AS INTEGER)"
            : 'MONTH(date)';

        $yearExpression = $driver === 'sqlite'
            ? "strftime('%Y', date) = ?"
            : 'YEAR(date) = ?';

        $currentYear = (string) now()->year;

        $monthlyRevenueRows = Booking::query()
            ->selectRaw($monthExpression.' as month, SUM(devis_estime) as total')
            ->whereRaw($yearExpression, [$currentYear])
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $monthlyMissionRows = Booking::query()
            ->selectRaw($monthExpression.' as month, COUNT(*) as total')
            ->whereRaw($yearExpression, [$currentYear])
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $monthlyRevenue = array_fill(1, 12, 0.0);
        $monthlyMissions = array_fill(1, 12, 0);

        foreach ($monthlyRevenueRows as $row) {
            $month = (int) $row->month;

            if ($month >= 1 && $month <= 12) {
                $monthlyRevenue[$month] = (float) $row->total;
            }
        }

        foreach ($monthlyMissionRows as $row) {
            $month = (int) $row->month;

            if ($month >= 1 && $month <= 12) {
                $monthlyMissions[$month] = (int) $row->total;
            }
        }

        $totalRevenue = (float) Booking::query()->sum('devis_estime');

        /*
         * LA MARGE DE LA PLATEFORME, C'EST SA COMMISSION — et elle est enfin lue quelque part.
         *
         * Cette valeur affichait zéro depuis toujours. Le code cherchait une colonne `margin` ou
         * `marge` qu'AUCUNE table n'a jamais portée, et interrogeait par-dessus le marché une table
         * différente de celle qu'il sommait. Deux gardes successives se refermaient donc sur rien,
         * et la carte « Marge totale » du tableau de bord annonçait 0,00 € avec l'aplomb d'un
         * calcul. Un chiffre faux affiché sans réserve est pire qu'une case vide : on le lit.
         *
         * `platform_fee_cents` est ce que la plateforme retient réellement. Il est écrit à la
         * complétion par MissionLifecycleService depuis CommissionService, et c'est la même valeur
         * que reprend l'écriture comptable (BookingPostingService).
         *
         * LES DEUX BASES DIFFÈRENT, et il faut le savoir avant de faire un ratio : le chiffre
         * d'affaires ci-dessus additionne des DEVIS (`devis_estime`), y compris pour des
         * réservations annulées ou jamais payées, tandis que la marge n'existe que sur les missions
         * effectivement terminées et encaissées. Rapporter l'une à l'autre ne donne pas un taux de
         * commission.
         */
        // Le transtypage n'est pas cosmétique : en PHP, `/` rend un ENTIER quand les deux opérandes
        // sont entiers et la division exacte. 2000/100 donnait donc `int(20)` et 4325/100
        // `float(43.25)` — le type de la clé changeait avec le montant, ce qui casse une comparaison
        // stricte et fait sérialiser tantôt `20`, tantôt `43.25` dans la même réponse.
        $totalMargin = (float) (((int) Booking::query()->sum('platform_fee_cents')) / 100);

        $missionsCount = Booking::query()->count();

        $completedMissions = Booking::query()
            ->whereIn('status', ['termine', 'terminé', 'completed'])
            ->count();

        $averageRating = 0.0;

        if (Schema::hasTable('feedbacks')) {
            if (Schema::hasColumn('feedbacks', 'note')) {
                $averageRating = (float) Feedback::query()->avg('note');
            } elseif (Schema::hasColumn('feedbacks', 'rating')) {
                $averageRating = (float) Feedback::query()->avg('rating');
            }
        }

        $statusBreakdown = Booking::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $averageTicket = $missionsCount > 0
            ? (float) Booking::query()->avg('devis_estime')
            : 0.0;

        return [
            // Clés attendues par admin-analytics-dashboard.blade.php
            'total_revenue' => $totalRevenue,
            'total_margin' => $totalMargin,
            'missions_count' => $missionsCount,
            'completed_missions' => $completedMissions,
            'average_rating' => round($averageRating, 2),
            'monthly_revenue' => array_values($monthlyRevenue),
            'monthly_missions' => array_values($monthlyMissions),

            // Clés conservées pour compatibilité avec d’autres composants
            'monthlyRevenue' => collect($monthlyRevenue)
                ->map(fn ($total, $month) => [
                    'month' => (int) $month,
                    'total' => (float) $total,
                ])
                ->values()
                ->all(),

            'monthlyMissions' => collect($monthlyMissions)
                ->map(fn ($total, $month) => [
                    'month' => (int) $month,
                    'total' => (int) $total,
                ])
                ->values()
                ->all(),

            'statusBreakdown' => $statusBreakdown,
            'totalRevenue' => $totalRevenue,
            'totalMargin' => $totalMargin,
            'averageTicket' => $averageTicket,
            'totalBookings' => $missionsCount,
        ];
    }
}

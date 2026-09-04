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

        // UNE RESERVATION ANNULEE N'EST PAS DU CHIFFRE D'AFFAIRES. La somme les comptait :
        // sur les donnees locales, 87,75 € d'une annulation gonflaient le total affiche.
        $statutsSansSuite = ['annule', 'annulé', 'refuse', 'refusé', 'cancelled', 'rejected'];

        $totalRevenue = (float) Booking::query()
            ->whereNotIn('status', $statutsSansSuite)
            ->sum('devis_estime');

        // LA MARGE DE LA PLATEFORME, C'EST SA COMMISSION — et elle est enfin lue quelque part.
        // Le transtypage n'est pas cosmétique : en PHP, `/` rend un ENTIER quand les deux opérandes
        // sont entiers et la division exacte. 2000/100 donnait donc `int(20)` et 4325/100
        // `float(43.25)` — le type de la clé changeait avec le montant, ce qui casse une comparaison
        // stricte et fait sérialiser tantôt `20`, tantôt `43.25` dans la même réponse.
        $totalMargin = (float) (((int) Booking::query()->sum('platform_fee_cents')) / 100);

        // MEME BASE QUE LE CA. Compter les annulations ici pendant que le chiffre d'affaires les
        // ecarte ferait un ticket moyen faux et un « terminees sur missions » qui ne tombe jamais juste.
        $missionsCount = Booking::query()->whereNotIn('status', $statutsSansSuite)->count();

        $completedMissions = Booking::query()
            ->whereIn('status', ['termine', 'terminé', 'completed'])
            ->count();

        $averageRating = 0.0;

        // LE GARDE INTERROGEAIT UNE AUTRE TABLE QUE LA REQUÊTE.
        $tableDesAvis = (new Feedback)->getTable();

        if (Schema::hasTable($tableDesAvis)) {
            $colonne = match (true) {
                Schema::hasColumn($tableDesAvis, 'note') => 'note',
                Schema::hasColumn($tableDesAvis, 'rating') => 'rating',
                default => null,
            };

            if ($colonne !== null) {
                $averageRating = (float) Feedback::query()->avg($colonne);
            }
        }

        $statusBreakdown = Booking::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $averageTicket = $missionsCount > 0
            ? (float) Booking::query()->whereNotIn('status', $statutsSansSuite)->avg('devis_estime')
            : 0.0;

        return [
            // Les cinq totaux de la section « Plateforme » du tableau de bord.
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

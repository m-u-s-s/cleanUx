<?php

namespace App\Livewire\Admin\Analytics;

use App\Models\Booking;
use App\Models\User;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;

/** Admin analytics : pivot des raisons d'annulation pour identifier les frictions. */
class CancellationReasonsCenter extends Component
{
    use EnforcesAdminAccess;

    public string $period = '30d';

    public string $groupBy = 'reason';

    public function setPeriod(string $period): void
    {
        $this->period = $period;
    }

    public function setGroupBy(string $g): void
    {
        $this->groupBy = $g;
    }

    public function render(): View
    {
        $since = match ($this->period) {
            '7d' => Carbon::now()->subDays(7),
            '30d' => Carbon::now()->subDays(30),
            '90d' => Carbon::now()->subDays(90),
            'all' => Carbon::create(2020, 1, 1),
            default => Carbon::now()->subDays(30),
        };

        $base = Booking::query()
            ->whereIn('status', ['annule', 'cancelled', 'canceled'])
            ->where(function ($q) use ($since) {
                $q->where('cancelled_at', '>=', $since)
                    ->orWhere('updated_at', '>=', $since);
            });

        $totalCancelled = (clone $base)->count();
        $totalAll = Booking::query()
            ->where(function ($q) use ($since) {
                $q->where('created_at', '>=', $since)
                    ->orWhere('updated_at', '>=', $since);
            })
            ->count();

        $cancellationRate = $totalAll > 0
            ? round(($totalCancelled / $totalAll) * 100, 2)
            : 0;

        $rows = (clone $base)
            // `cancellation_fee_amount` est un `decimal(10,2)` EN EUROS — son alias annonçait des centimes, et la vue divisait donc le total par cent.
            ->selectRaw('cancellation_reason, COUNT(*) as count, SUM(COALESCE(cancellation_fee_amount,0)) as total_fee_euros')
            ->whereNotNull('cancellation_reason')
            ->where('cancellation_reason', '!=', '')
            ->groupBy('cancellation_reason')
            ->orderByDesc('count')
            ->limit(30)
            ->get();

        $byCancelledBy = (clone $base)
            ->selectRaw('cancelled_by, COUNT(*) as count')
            ->whereNotNull('cancelled_by')
            ->groupBy('cancelled_by')
            ->get();

        // `bookings.cancelled_by` EST UN IDENTIFIANT, et la carte l'affichait tel quel :
        // « Annulé par 3 » ne dit rien a personne. Une requete pour toute la colonne.
        $noms = User::query()
            ->whereIn('id', $byCancelledBy->pluck('cancelled_by')->filter()->all())
            ->pluck('name', 'id');

        // DES TABLEAUX, PAS DES MODELES : une colonne agregee posee sur un `Booking` fait
        // croire a une propriete qui n'existe pas, et PHPStan le dit.
        $byCancelledBy = $byCancelledBy->map(fn ($ligne) => [
            'nom' => $noms[(int) $ligne->getAttribute('cancelled_by')]
                ?? 'Utilisateur #'.$ligne->getAttribute('cancelled_by'),
            'count' => (int) $ligne->getAttribute('count'),
        ]);

        return view('livewire.admin.analytics.cancellation-reasons-center', [
            'totalCancelled' => $totalCancelled,
            'totalAll' => $totalAll,
            'cancellationRate' => $cancellationRate,
            'rows' => $rows,
            'byCancelledBy' => $byCancelledBy,
        ]);
    }
}

<?php

namespace App\Livewire\Admin\Analytics;

use App\Models\Booking;
use App\Models\BookingCancellationV2;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * Admin analytics : pivot des raisons d'annulation pour identifier les frictions.
 *
 * `booking_cancellations_v2` FAIT FOI. Cet ecran lisait `bookings.cancellation_reason` et
 * `bookings.cancellation_fee_amount` : deux colonnes miroir qu'un second service ecrit sans
 * passer par le moteur, si bien que la meme annulation s'affichait a 87,75 € de frais ici et
 * a 0 € dans l'onglet voisin.
 */
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
            '90d' => Carbon::now()->subDays(90),
            'all' => Carbon::create(2020, 1, 1),
            default => Carbon::now()->subDays(30),
        };

        $base = BookingCancellationV2::query()->where('cancelled_at', '>=', $since);

        $totalCancelled = (clone $base)->count();

        // LE DENOMINATEUR RESTE LES RESERVATIONS : un taux d'annulation se mesure sur ce qui
        // aurait pu etre annule, pas sur les annulations elles-memes.
        $totalAll = Booking::query()
            ->where(function ($q) use ($since) {
                $q->where('created_at', '>=', $since)
                    ->orWhere('updated_at', '>=', $since);
            })
            ->count();

        $cancellationRate = $totalAll > 0
            ? round(($totalCancelled / $totalAll) * 100, 2)
            : 0;

        // Le motif libre d'abord, le code ensuite : une annulation sans texte porte quand meme
        // le palier qui l'a tarifee.
        $motif = "COALESCE(NULLIF(reason_text, ''), NULLIF(reason_code, ''))";

        $rows = (clone $base)
            ->selectRaw($motif.' as raison, COUNT(*) as total, SUM(COALESCE(fee_amount_cents, 0)) as frais_cents')
            ->whereRaw($motif.' IS NOT NULL')
            ->groupBy('raison')
            ->orderByDesc('total')
            ->limit(30)
            ->get()
            ->map(fn ($ligne) => [
                'raison' => (string) $ligne->getAttribute('raison'),
                'count' => (int) $ligne->getAttribute('total'),
                'frais_euros' => round(((int) $ligne->getAttribute('frais_cents')) / 100, 2),
            ]);

        // « ANNULE PAR » VOULAIT DIRE LE ROLE, PAS L'IDENTIFIANT. L'ecran lisait
        // `bookings.cancelled_by`, une colonne d'identifiants, et affichait « Annule par 3 ».
        $byActorRole = (clone $base)
            ->selectRaw('actor_role, COUNT(*) as total')
            ->whereNotNull('actor_role')
            ->groupBy('actor_role')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($ligne) => [
                'role' => (string) $ligne->getAttribute('actor_role'),
                'count' => (int) $ligne->getAttribute('total'),
            ]);

        return view('livewire.admin.analytics.cancellation-reasons-center', [
            'totalCancelled' => $totalCancelled,
            'totalAll' => $totalAll,
            'cancellationRate' => $cancellationRate,
            'rows' => $rows,
            'byActorRole' => $byActorRole,
        ]);
    }
}

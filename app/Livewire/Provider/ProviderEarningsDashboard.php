<?php

namespace App\Livewire\Provider;

use App\Models\Booking;
use App\Models\BookingTip;
use App\Models\ProviderWalletTransaction;
use App\Models\Trade;
use App\Services\Payments\ExpressPayoutService;
use App\Services\Provider\OfferStatsService;
use App\Services\Provider\TaxSummaryService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dashboard provider earnings (revenus jour/semaine/mois + tips + projections).
 *
 * Lit depuis :
 *   - bookings (status=termine, amount captured)
 *   - booking_tips (status=charged/paid_out)
 *   - provider_wallet_transactions (ledger immuable provider)
 *
 * Toutes les agrégations sont per-period (today / this_week / this_month + prev period).
 */
class ProviderEarningsDashboard extends Component
{
    public string $period = 'week';   // today | week | month | year

    public function setPeriod(string $period): void
    {
        $this->period = $period;
    }

    /** L'année du résumé fiscal (E18). */
    public int $anneeFiscale = 0;

    /** Le montant du virement instantané demandé (E14), en euros saisis. */
    public string $montantExpress = '';

    #[Locked]
    public ?string $refusExpress = null;

    public function mount(): void
    {
        // L'année COURANTE par défaut : celle qu'on regarde onze mois sur douze. Janvier fait
        // exception et se corrige d'un clic.
        $this->anneeFiscale = (int) Carbon::now()->year;
    }

    /**
     * DEMANDER UN VIREMENT INSTANTANÉ (E14).
     *
     * Le refus du domaine s'AFFICHE : « le virement instantané demande au moins 20 € » est une
     * règle à lire, et la remplacer par une erreur générique ferait recommencer la saisie.
     */
    public function demanderLeVirementExpress(): void
    {
        $cents = (int) round(((float) str_replace(',', '.', $this->montantExpress)) * 100);

        try {
            app(ExpressPayoutService::class)->demander(Auth::user(), $cents);

            $this->reset(['montantExpress', 'refusExpress']);
        } catch (ValidationException $e) {
            $this->refusExpress = collect($e->errors())->flatten()->first();
        }
    }

    /** L'export fiscal de l'année — le fichier qu'on donne à son comptable. */
    public function exporterLesRevenus(): StreamedResponse
    {
        $export = app(TaxSummaryService::class)->csv(Auth::user(), (int) $this->anneeFiscale);

        return response()->streamDownload(
            fn () => print ($export['content']),
            $export['filename'],
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function render(): View
    {
        $user = Auth::user();
        [$start, $end, $prevStart, $prevEnd, $bucketFormat] = $this->periodRanges();

        $current = $this->aggregate($user->id, $start, $end);
        $previous = $this->aggregate($user->id, $prevStart, $prevEnd);

        $delta = $this->deltaPercent($current['gross_cents'], $previous['gross_cents']);
        $missionsDelta = $this->deltaPercent($current['missions_count'], $previous['missions_count']);

        // Timeseries pour graph
        $series = $this->timeseries($user->id, $start, $end, $bucketFormat);

        // Top métiers
        $topTrades = $this->topTrades($user->id, $start, $end);

        return view('livewire.provider.provider-earnings-dashboard', [
            'period' => $this->period,
            /*
             * E15 — LES STATISTIQUES D'OFFRES. Tout est déjà dans `mission_assignments` et personne
             * ne le lit : c'est la réponse exacte à « pourquoi est-ce que je reçois moins de
             * courses qu'avant », une question à laquelle on ne pouvait répondre qu'au ressenti.
             */
            'offres' => app(OfferStatsService::class)->pour($user),
            /*
             * E18 — L'ASSISTANT FISCAL. Le registre est immuable et daté ; il n'existait aucun
             * moyen d'en sortir un total autrement qu'en additionnant des virements à la main, en
             * avril, sous pression.
             */
            'fiscal' => app(TaxSummaryService::class)->pourLAnnee($user, (int) $this->anneeFiscale),
            // E14 — le devis du virement instantané, affiché AVANT le bouton : « 1,5 % » se lit et
            // ne se comprend pas, « 2,40 € » se comprend.
            'devisExpress' => app(ExpressPayoutService::class)->devis(
                (int) round(((float) str_replace(',', '.', $this->montantExpress ?: '0')) * 100),
            ),
            'current' => $current,
            'previous' => $previous,
            'delta' => $delta,
            'missionsDelta' => $missionsDelta,
            'series' => $series,
            'topTrades' => $topTrades,
        ])->layout('layouts.app');
    }

    protected function periodRanges(): array
    {
        $now = Carbon::now();

        return match ($this->period) {
            'today' => [
                $now->copy()->startOfDay(),
                $now->copy()->endOfDay(),
                $now->copy()->subDay()->startOfDay(),
                $now->copy()->subDay()->endOfDay(),
                'H',
            ],
            'week' => [
                $now->copy()->startOfWeek(),
                $now->copy()->endOfWeek(),
                $now->copy()->subWeek()->startOfWeek(),
                $now->copy()->subWeek()->endOfWeek(),
                'D',
            ],
            'month' => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
                'd/m',
            ],
            'year' => [
                $now->copy()->startOfYear(),
                $now->copy()->endOfYear(),
                $now->copy()->subYear()->startOfYear(),
                $now->copy()->subYear()->endOfYear(),
                'M',
            ],
            default => [
                $now->copy()->startOfWeek(),
                $now->copy()->endOfWeek(),
                $now->copy()->subWeek()->startOfWeek(),
                $now->copy()->subWeek()->endOfWeek(),
                'D',
            ],
        };
    }

    protected function aggregate(int $userId, Carbon $start, Carbon $end): array
    {
        $missionsQuery = Booking::query()
            ->intervenantEst($userId)
            ->whereIn('status', ['termine', 'completed', 'closed'])
            ->whereBetween('updated_at', [$start, $end]);

        $missionsCount = (clone $missionsQuery)->count();
        $grossCentsFromBookings = (int) (clone $missionsQuery)
            ->sum(DB::raw('COALESCE(provider_amount_cents, payment_amount_cents, ROUND(devis_estime * 100))'));

        $tipsCents = 0;
        if (Schema::hasTable('booking_tips')) {
            $tipsCents = (int) BookingTip::query()
                ->where('provider_user_id', $userId)
                ->whereIn('status', [BookingTip::STATUS_CHARGED, BookingTip::STATUS_PAID_OUT])
                ->whereBetween('created_at', [$start, $end])
                ->sum('amount_cents');
        }

        /*
         * DEUX COLONNES INEXISTANTES DANS LE MÊME BLOC — la section « portefeuille » de cette page
         * n'a jamais rien pu afficher.
         *
         * Le filtre portait sur `user_id`, la colonne s'appelle `provider_user_id` ; la somme
         * portait sur `amount_cents`, la colonne s'appelle `amount` et vaut des EUROS. Sur MySQL,
         * chacune lève « Unknown column ». Sur SQLite — le moteur de la suite de tests — Laravel
         * entoure les identifiants de guillemets doubles et SQLite traite un identifiant inconnu
         * comme une CHAÎNE LITTÉRALE : la comparaison est fausse en silence et la somme rend zéro.
         *
         * Le défaut était donc invisible aux tests ET masqué par une table vide. Même un paiement
         * réellement encaissé n'aurait rien affiché ici.
         */
        $walletEarnedCents = 0;
        $walletPaidOutCents = 0;
        if (Schema::hasTable('provider_wallet_transactions')) {
            $base = ProviderWalletTransaction::query()
                ->where('provider_user_id', $userId)
                ->whereBetween('created_at', [$start, $end]);

            $walletEarnedCents = (int) round(((float) (clone $base)->where('direction', 'credit')->sum('amount')) * 100);
            $walletPaidOutCents = (int) round(((float) (clone $base)->where('type', 'payout')->sum('amount')) * 100);
        }

        return [
            'missions_count' => $missionsCount,
            'gross_cents' => $grossCentsFromBookings + $tipsCents,
            'mission_cents' => $grossCentsFromBookings,
            'tips_cents' => $tipsCents,
            'wallet_credited_cents' => $walletEarnedCents,
            'wallet_paid_out_cents' => $walletPaidOutCents,
        ];
    }

    protected function timeseries(int $userId, Carbon $start, Carbon $end, string $bucketFormat): array
    {
        // Simple bucketization en PHP — pour scale, basculer sur SQL GROUP BY DATE_FORMAT
        $bookings = Booking::query()
            ->intervenantEst($userId)
            ->whereIn('status', ['termine', 'completed', 'closed'])
            ->whereBetween('updated_at', [$start, $end])
            ->get(['updated_at', 'provider_amount_cents', 'payment_amount_cents', 'devis_estime']);

        $buckets = [];
        foreach ($bookings as $b) {
            $key = $b->updated_at->format($bucketFormat);
            $cents = (int) ($b->provider_amount_cents ?? $b->payment_amount_cents ?? round(((float) $b->devis_estime) * 100));
            $buckets[$key] = ($buckets[$key] ?? 0) + $cents;
        }

        return array_map(fn ($key, $cents) => [
            'label' => $key,
            'amount_cents' => $cents,
            'amount_eur' => round($cents / 100, 2),
        ], array_keys($buckets), array_values($buckets));
    }

    protected function topTrades(int $userId, Carbon $start, Carbon $end): array
    {
        if (! Schema::hasColumn('bookings', 'trade_id')) {
            return [];
        }
        $rows = Booking::query()
            ->intervenantEst($userId)
            ->whereIn('status', ['termine', 'completed', 'closed'])
            ->whereBetween('updated_at', [$start, $end])
            ->whereNotNull('trade_id')
            ->select('trade_id', DB::raw('COUNT(*) as missions'), DB::raw('SUM(COALESCE(provider_amount_cents, payment_amount_cents, ROUND(devis_estime * 100))) as total_cents'))
            ->groupBy('trade_id')
            ->orderByDesc('total_cents')
            ->limit(5)
            ->get();

        return $rows->map(function ($r) {
            $trade = Trade::find($r->trade_id);

            return [
                'trade_name' => $trade?->name ?? 'Trade #'.$r->trade_id,
                'missions' => (int) $r->missions,
                'total_eur' => round(((int) $r->total_cents) / 100, 2),
            ];
        })->toArray();
    }

    protected function deltaPercent(int|float $current, int|float $previous): ?float
    {
        if ($previous === 0 || $previous === 0.0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}

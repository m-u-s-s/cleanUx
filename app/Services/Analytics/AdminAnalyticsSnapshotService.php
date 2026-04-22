<?php

namespace App\Services\Analytics;

use App\Models\RendezVous;
use App\Services\Finance\FinanceDocumentService;
use Illuminate\Support\Collection;

class AdminAnalyticsSnapshotService
{
    public function __construct(
        protected FinanceDocumentService $financeService
    ) {
    }

    public function monthTrend(Collection $rows): Collection
    {
        return $rows
            ->groupBy(fn (RendezVous $rdv) => optional($rdv->date)->format('Y-m') ?: 'sans-date')
            ->map(function (Collection $items, string $monthKey) {
                $turnover = $items->sum(fn (RendezVous $rdv) => $this->financeService->amountBreakdownFor($rdv)['subtotal']);
                $margin = $items->sum(fn (RendezVous $rdv) => $this->financeService->amountBreakdownFor($rdv)['estimated_margin_amount']);

                return [
                    'month' => $monthKey,
                    'count' => $items->count(),
                    'completed' => $items->where('status', 'termine')->count(),
                    'turnover' => round((float) $turnover, 2),
                    'margin' => round((float) $margin, 2),
                ];
            })
            ->sortKeys()
            ->values();
    }
}

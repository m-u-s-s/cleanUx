<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsExporter;
use App\Services\Analytics\DateRangeResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Phase 7 — Endpoints d'export pour le dashboard analytics. */
class AnalyticsExportController extends Controller
{
    public function __construct(
        protected AnalyticsExporter $exporter,
        protected DateRangeResolver $dateResolver,
    ) {}

    public function kpis(Request $request): StreamedResponse
    {
        [$from, $to] = $this->resolvePeriod($request);

        return $this->exporter->kpisCsv(
            $request->user()->organization_account_id,
            $from,
            $to
        );
    }

    public function monthlyRevenue(Request $request): StreamedResponse
    {
        $months = (int) $request->query('months', 12);
        $months = max(1, min(36, $months)); // entre 1 et 36 mois

        return $this->exporter->monthlyRevenueCsv(
            $request->user()->organization_account_id,
            $months
        );
    }

    public function bookings(Request $request): StreamedResponse
    {
        [$from, $to] = $this->resolvePeriod($request);

        return $this->exporter->bookingsDetailedCsv(
            $request->user()->organization_account_id,
            $from,
            $to
        );
    }

    private function resolvePeriod(Request $request): array
    {
        $preset = $request->query('preset', 'last_30d');
        $from = $request->query('from');
        $to = $request->query('to');

        [$fromCarbon, $toCarbon] = $this->dateResolver->resolve($preset, $from, $to);

        return [$fromCarbon, $toCarbon];
    }
}

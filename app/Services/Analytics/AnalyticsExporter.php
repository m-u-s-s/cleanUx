<?php

namespace App\Services\Analytics;

use App\Models\User;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase 7 — Exports analytiques pour analyse externe (Excel, BI tools).
 *
 * Le format CSV est volontairement plat (1 ligne = 1 enregistrement) pour
 * être directement importable dans Excel, Power BI, Tableau, etc.
 *
 * 3 exports disponibles :
 *   - kpis.csv               : KPIs principaux + comparaison période précédente
 *   - monthly-revenue.csv    : revenu mensuel sur N mois
 *   - bookings-detailed.csv  : tous les bookings de la période avec détails
 */
class AnalyticsExporter
{
    public function __construct(
        protected AnalyticsKpiService $kpis,
        protected DateRangeResolver $dateResolver,
    ) {}

    public function kpisCsv(?int $organizationAccountId, CarbonImmutable $from, CarbonImmutable $to): StreamedResponse
    {
        $filename = sprintf('kpis_%s_%s.csv', $from->toDateString(), $to->toDateString());

        return $this->csvResponse($filename, function ($handle) use ($organizationAccountId, $from, $to) {
            $kpis = $this->kpis->mainKpis($organizationAccountId, $from, $to);

            fputcsv($handle, ['Indicateur', 'Valeur', 'Unité', 'Évolution (%)', 'Période'], ';');

            $period = $from->format('d/m/Y') . ' → ' . $to->format('d/m/Y');

            fputcsv($handle, ['Chiffre d\'affaires', $kpis['revenue']['value'], 'EUR', $kpis['revenue']['trend'] ?? '—', $period], ';');
            fputcsv($handle, ['Nombre de rendez-vous', $kpis['bookings_count']['value'], 'unité', $kpis['bookings_count']['trend'] ?? '—', $period], ';');
            fputcsv($handle, ['Rendez-vous terminés', $kpis['completed_count']['value'], 'unité', $kpis['completed_count']['completion_rate'] ?? '—', $period], ';');
            fputcsv($handle, ['Taux d\'annulation', $kpis['cancellation_rate']['value'], '%', $kpis['cancellation_rate']['trend'] ?? '—', $period], ';');
            fputcsv($handle, ['Satisfaction moyenne', $kpis['average_rating']['value'] ?? '—', '/5', '—', $period], ';');
            fputcsv($handle, ['Sites actifs', $kpis['active_sites']['value'], 'sur ' . $kpis['active_sites']['total'], '—', $period], ';');
        });
    }

    public function monthlyRevenueCsv(?int $organizationAccountId, int $months = 12): StreamedResponse
    {
        $filename = sprintf('revenue_mensuel_%dm.csv', $months);

        return $this->csvResponse($filename, function ($handle) use ($organizationAccountId, $months) {
            $rows = $this->kpis->monthlyRevenue($organizationAccountId, $months);

            fputcsv($handle, ['Mois', 'Libellé', 'Chiffre d\'affaires (€)', 'Nombre de rendez-vous'], ';');

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['month'],
                    $row['label'],
                    number_format($row['revenue'], 2, ',', ''),
                    $row['bookings_count'],
                ], ';');
            }
        });
    }

    public function bookingsDetailedCsv(?int $organizationAccountId, CarbonImmutable $from, CarbonImmutable $to): StreamedResponse
    {
        $filename = sprintf('bookings_detailed_%s_%s.csv', $from->toDateString(), $to->toDateString());

        return $this->csvResponse($filename, function ($handle) use ($organizationAccountId, $from, $to) {
            fputcsv($handle, [
                'Référence',
                'Date',
                'Heure',
                'Statut',
                'Service',
                'Site',
                'Adresse',
                'Ville',
                'Code postal',
                'Surface (m²)',
                'Prix estimé (€)',
                'Mode',
                'Créé le',
                'Terminé le',
                'Annulé le',
            ], ';');

            $query = \App\Models\Booking::query()
                ->with(['serviceCatalog:id,name', 'organizationSite:id,name'])
                ->whereBetween('scheduled_date', [$from->toDateString(), $to->toDateString()]);

            if ($organizationAccountId) {
                $query->where('customer_organization_id', $organizationAccountId);
            }

            $query->chunkById(500, function ($bookings) use ($handle) {
                foreach ($bookings as $b) {
                    fputcsv($handle, [
                        $b->booking_reference,
                        $b->scheduled_date instanceof \Carbon\Carbon ? $b->scheduled_date->format('Y-m-d') : (string) $b->scheduled_date,
                        $b->scheduled_time ? \Carbon\Carbon::parse($b->scheduled_time)->format('H:i') : '',
                        $b->status,
                        $b->serviceCatalog?->name ?? '',
                        $b->organizationSite?->name ?? '',
                        $b->address ?? '',
                        $b->city ?? '',
                        $b->postal_code ?? '',
                        $b->surface_m2 ? (int) $b->surface_m2 : '',
                        $b->estimated_price ? number_format((float) $b->estimated_price, 2, ',', '') : '',
                        $b->booking_mode ?? '',
                        $b->created_at?->format('Y-m-d H:i'),
                        in_array($b->status, ['termine', 'completed']) ? $b->updated_at?->format('Y-m-d H:i') : '',
                        in_array($b->status, ['annule', 'cancelled', 'refuse']) ? $b->updated_at?->format('Y-m-d H:i') : '',
                    ], ';');
                }
            });
        });
    }

    private function csvResponse(string $filename, callable $writer): StreamedResponse
    {
        return new StreamedResponse(function () use ($writer) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel
            $writer($handle);
            fclose($handle);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-cache',
        ]);
    }
}

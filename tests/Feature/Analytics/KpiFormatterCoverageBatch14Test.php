<?php

namespace Tests\Feature\Analytics;

use App\Services\Analytics\KpiFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Tests\TestCase;

class KpiFormatterCoverageBatch14Test extends TestCase
{
    private function formatter(): KpiFormatter
    {
        return app(KpiFormatter::class);
    }

    private function row(array $attributes): object
    {
        return (object) $attributes;
    }

    public function test_status_label_maps_known_and_unknown_statuses(): void
    {
        $f = $this->formatter();

        $this->assertSame('En attente', $f->statusLabel('pending_approval'));
        $this->assertSame('Confirmé', $f->statusLabel('confirmed'));
        $this->assertSame('En route', $f->statusLabel('on_route'));
        $this->assertSame('Sur place', $f->statusLabel('in_progress'));
        $this->assertSame('Terminé', $f->statusLabel('done'));
        $this->assertSame('Annulé', $f->statusLabel('refused'));
        $this->assertSame('Weird_state', $f->statusLabel('weird_state'));
    }

    public function test_status_color_maps_known_and_unknown_statuses(): void
    {
        $f = $this->formatter();

        $this->assertSame('#f59e0b', $f->statusColor('en_attente'));
        $this->assertSame('#3b82f6', $f->statusColor('confirme'));
        $this->assertSame('#8b5cf6', $f->statusColor('en_route'));
        $this->assertSame('#06b6d4', $f->statusColor('sur_place'));
        $this->assertSame('#10b981', $f->statusColor('termine'));
        $this->assertSame('#ef4444', $f->statusColor('annule'));
        $this->assertSame('#64748b', $f->statusColor('unknown'));
    }

    public function test_scalar_kpi_builders_return_expected_shapes(): void
    {
        $f = $this->formatter();

        $revenue = $f->revenueKpi(1234.567, 1000.0, 23.4);
        $this->assertSame(1234.57, $revenue['value']);
        $this->assertSame('EUR', $revenue['currency']);
        $this->assertSame(23.4, $revenue['trend']);
        $this->assertSame("Chiffre d'affaires", $revenue['label']);

        $bookings = $f->bookingsCountKpi(42, null);
        $this->assertSame(42, $bookings['value']);
        $this->assertNull($bookings['trend']);
        $this->assertSame('Rendez-vous', $bookings['label']);

        $completed = $f->completedKpi(10, 80.5);
        $this->assertSame(10, $completed['value']);
        $this->assertSame(80.5, $completed['completion_rate']);
        $this->assertSame('Terminés', $completed['label']);

        $cancellation = $f->cancellationRateKpi(12.5, -3.0);
        $this->assertSame(12.5, $cancellation['value']);
        $this->assertSame(-3.0, $cancellation['trend']);
        $this->assertSame("Taux d'annulation", $cancellation['label']);

        $rating = $f->averageRatingKpi(4.6, 18);
        $this->assertSame(4.6, $rating['value']);
        $this->assertSame(18, $rating['count']);
        $this->assertSame('Satisfaction moyenne', $rating['label']);

        $sites = $f->activeSitesKpi(3, 5);
        $this->assertSame(3, $sites['value']);
        $this->assertSame(5, $sites['total']);
        $this->assertSame('Sites actifs', $sites['label']);
    }

    public function test_fill_monthly_revenue_series_fills_gaps_with_zeros(): void
    {
        $f = $this->formatter();
        $from = CarbonImmutable::parse('2026-01-01');
        $to = CarbonImmutable::parse('2026-03-01');

        $rows = collect([
            '2026-01' => $this->row(['revenue' => '150.5', 'bookings_count' => '4']),
            '2026-03' => $this->row(['revenue' => 99.0, 'bookings_count' => 2]),
        ]);

        $series = $f->fillMonthlyRevenueSeries($rows, $from, $to);

        $this->assertInstanceOf(Collection::class, $series);
        $this->assertCount(3, $series);

        $january = $series->firstWhere('month', '2026-01');
        $this->assertSame(150.5, $january['revenue']);
        $this->assertSame(4, $january['bookings_count']);
        $this->assertIsString($january['label']);

        $february = $series->firstWhere('month', '2026-02');
        $this->assertSame(0.0, $february['revenue']);
        $this->assertSame(0, $february['bookings_count']);

        $march = $series->firstWhere('month', '2026-03');
        $this->assertSame(99.0, $march['revenue']);
        $this->assertSame(2, $march['bookings_count']);
    }

    public function test_fill_satisfaction_series_uses_null_for_missing_months(): void
    {
        $f = $this->formatter();
        $from = CarbonImmutable::parse('2026-01-01');
        $to = CarbonImmutable::parse('2026-02-01');

        $rows = collect([
            '2026-02' => $this->row(['avg_rating' => '4.333', 'count' => '6']),
        ]);

        $series = $f->fillSatisfactionSeries($rows, $from, $to);

        $this->assertCount(2, $series);

        $january = $series->firstWhere('month', '2026-01');
        $this->assertNull($january['avg_rating']);
        $this->assertSame(0, $january['count']);

        $february = $series->firstWhere('month', '2026-02');
        $this->assertSame(4.33, $february['avg_rating']);
        $this->assertSame(6, $february['count']);
    }

    public function test_format_status_breakdown_casts_counts(): void
    {
        $f = $this->formatter();

        $rows = collect([
            $this->row(['status' => 'termine', 'count' => '12']),
            $this->row(['status' => 'annule', 'count' => 3]),
        ]);

        $formatted = $f->formatStatusBreakdown($rows)->values();

        $this->assertSame('termine', $formatted[0]['status']);
        $this->assertSame(12, $formatted[0]['count']);
        $this->assertSame(3, $formatted[1]['count']);
    }

    public function test_format_top_services_defaults_revenue_when_absent(): void
    {
        $f = $this->formatter();

        $rows = collect([
            $this->row(['service_name' => 'Nettoyage', 'count' => '7', 'revenue' => '480.25']),
            $this->row(['service_name' => 'Peinture', 'count' => 2]),
        ]);

        $formatted = $f->formatTopServices($rows)->values();

        $this->assertSame('Nettoyage', $formatted[0]['service_name']);
        $this->assertSame(7, $formatted[0]['count']);
        $this->assertSame(480.25, $formatted[0]['revenue']);
        $this->assertSame(0.0, $formatted[1]['revenue']);
    }

    public function test_format_top_sites_casts_all_columns(): void
    {
        $f = $this->formatter();

        $rows = collect([
            $this->row(['site_id' => '9', 'site_name' => 'Siège', 'count' => '5', 'revenue' => '750.5']),
        ]);

        $formatted = $f->formatTopSites($rows)->values();

        $this->assertSame(9, $formatted[0]['site_id']);
        $this->assertSame('Siège', $formatted[0]['site_name']);
        $this->assertSame(5, $formatted[0]['count']);
        $this->assertSame(750.5, $formatted[0]['revenue']);
    }
}

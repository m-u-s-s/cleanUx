<?php

namespace Tests\Feature\Analytics;

use App\Models\Booking;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\User;
use App\Services\Analytics\AnalyticsExporter;
use App\Services\Analytics\AnalyticsKpiService;
use App\Services\Analytics\DateRangeResolver;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function makeBooking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'booking_reference' => 'CUX-' . strtoupper(Str::random(6)),
            'scheduled_date'    => Carbon::today()->toDateString(),
            'scheduled_time'    => '09:00:00',
            'status'            => 'confirme',
            'estimated_price'   => 100.00,
            'currency'          => 'EUR',
            'priority'          => 'normal',
            'booking_mode'      => 'scheduled',
        ], $overrides));
    }

    // ──────────────────────────────────────────────────────
    // DateRangeResolver
    // ──────────────────────────────────────────────────────

    public function test_resolver_handles_last_7d(): void
    {
        $resolver = app(DateRangeResolver::class);
        [$from, $to, $label] = $resolver->resolve('last_7d');

        $this->assertSame(CarbonImmutable::today()->subDays(6)->toDateString(), $from->toDateString());
        $this->assertSame(CarbonImmutable::today()->toDateString(), $to->toDateString());
        $this->assertStringContainsString('7', $label);
    }

    public function test_resolver_handles_this_month(): void
    {
        $resolver = app(DateRangeResolver::class);
        [$from, $to] = $resolver->resolve('this_month');

        $this->assertSame(CarbonImmutable::today()->startOfMonth()->toDateString(), $from->toDateString());
        $this->assertSame(CarbonImmutable::today()->endOfMonth()->toDateString(), $to->toDateString());
    }

    public function test_resolver_handles_custom_dates(): void
    {
        $resolver = app(DateRangeResolver::class);
        [$from, $to] = $resolver->resolve('custom', '2026-01-01', '2026-03-31');

        $this->assertSame('2026-01-01', $from->toDateString());
        $this->assertSame('2026-03-31', $to->toDateString());
    }

    public function test_resolver_swaps_inverted_custom_dates(): void
    {
        $resolver = app(DateRangeResolver::class);
        [$from, $to] = $resolver->resolve('custom', '2026-03-31', '2026-01-01');

        $this->assertSame('2026-01-01', $from->toDateString());
        $this->assertSame('2026-03-31', $to->toDateString());
    }

    public function test_resolver_falls_back_for_unknown_preset(): void
    {
        $resolver = app(DateRangeResolver::class);
        [$from, $to] = $resolver->resolve('invalid_preset_xyz');

        // Doit retomber sur last_30d
        $this->assertSame(CarbonImmutable::today()->subDays(29)->toDateString(), $from->toDateString());
    }

    // ──────────────────────────────────────────────────────
    // AnalyticsKpiService - revenue
    // ──────────────────────────────────────────────────────

    public function test_revenue_kpi_sums_bookings_in_period(): void
    {
        $org = OrganizationAccount::factory()->create();

        $today = Carbon::today();
        $this->makeBooking(['customer_organization_id' => $org->id, 'scheduled_date' => $today, 'estimated_price' => 100]);
        $this->makeBooking(['customer_organization_id' => $org->id, 'scheduled_date' => $today, 'estimated_price' => 250]);
        $this->makeBooking(['customer_organization_id' => $org->id, 'scheduled_date' => $today->copy()->subYear(), 'estimated_price' => 500]); // hors période

        $kpis = app(AnalyticsKpiService::class);
        $result = $kpis->mainKpis(
            $org->id,
            CarbonImmutable::today()->startOfDay(),
            CarbonImmutable::today()->endOfDay()
        );

        $this->assertSame(350.0, $result['revenue']['value']);
        $this->assertSame(2, $result['bookings_count']['value']);
    }

    public function test_revenue_kpi_excludes_cancelled_bookings(): void
    {
        $org = OrganizationAccount::factory()->create();

        $this->makeBooking(['customer_organization_id' => $org->id, 'estimated_price' => 100, 'status' => 'confirme']);
        $this->makeBooking(['customer_organization_id' => $org->id, 'estimated_price' => 200, 'status' => 'annule']);
        $this->makeBooking(['customer_organization_id' => $org->id, 'estimated_price' => 300, 'status' => 'termine']);

        $kpis = app(AnalyticsKpiService::class);
        $result = $kpis->mainKpis(
            $org->id,
            CarbonImmutable::today()->startOfDay(),
            CarbonImmutable::today()->endOfDay()
        );

        // 100 + 300 = 400 (annule exclu)
        $this->assertSame(400.0, $result['revenue']['value']);
    }

    public function test_cancellation_rate_kpi(): void
    {
        $org = OrganizationAccount::factory()->create();

        $this->makeBooking(['customer_organization_id' => $org->id, 'status' => 'confirme']);
        $this->makeBooking(['customer_organization_id' => $org->id, 'status' => 'termine']);
        $this->makeBooking(['customer_organization_id' => $org->id, 'status' => 'annule']);
        $this->makeBooking(['customer_organization_id' => $org->id, 'status' => 'annule']);

        $kpis = app(AnalyticsKpiService::class);
        $result = $kpis->mainKpis(
            $org->id,
            CarbonImmutable::today()->startOfDay(),
            CarbonImmutable::today()->endOfDay()
        );

        // 2 annulés / 4 total = 50%
        $this->assertSame(50.0, $result['cancellation_rate']['value']);
    }

    public function test_completion_rate_kpi(): void
    {
        $org = OrganizationAccount::factory()->create();

        $this->makeBooking(['customer_organization_id' => $org->id, 'status' => 'termine']);
        $this->makeBooking(['customer_organization_id' => $org->id, 'status' => 'termine']);
        $this->makeBooking(['customer_organization_id' => $org->id, 'status' => 'confirme']);
        $this->makeBooking(['customer_organization_id' => $org->id, 'status' => 'confirme']);

        $kpis = app(AnalyticsKpiService::class);
        $result = $kpis->mainKpis(
            $org->id,
            CarbonImmutable::today()->startOfDay(),
            CarbonImmutable::today()->endOfDay()
        );

        $this->assertSame(2, $result['completed_count']['value']);
        $this->assertSame(50.0, $result['completed_count']['completion_rate']);
    }

    // ──────────────────────────────────────────────────────
    // AnalyticsKpiService - séries temporelles
    // ──────────────────────────────────────────────────────

    public function test_monthly_revenue_returns_continuous_series(): void
    {
        $org = OrganizationAccount::factory()->create();

        $this->makeBooking([
            'customer_organization_id' => $org->id,
            'scheduled_date' => Carbon::today(),
            'estimated_price' => 500,
        ]);

        $kpis = app(AnalyticsKpiService::class);
        $series = $kpis->monthlyRevenue($org->id, 6);

        // 6 mois = 6 entries (continues, même si zéro)
        $this->assertCount(6, $series);

        // La dernière entrée est le mois courant avec 500€
        $lastMonth = $series->last();
        $this->assertSame(Carbon::today()->format('Y-m'), $lastMonth['month']);
        $this->assertSame(500.0, $lastMonth['revenue']);
    }

    public function test_status_breakdown_returns_grouped_counts(): void
    {
        $org = OrganizationAccount::factory()->create();

        $this->makeBooking(['customer_organization_id' => $org->id, 'status' => 'confirme']);
        $this->makeBooking(['customer_organization_id' => $org->id, 'status' => 'confirme']);
        $this->makeBooking(['customer_organization_id' => $org->id, 'status' => 'termine']);

        $kpis = app(AnalyticsKpiService::class);
        $breakdown = $kpis->statusBreakdown(
            $org->id,
            CarbonImmutable::today()->startOfDay(),
            CarbonImmutable::today()->endOfDay()
        );

        $this->assertSame(2, $breakdown->count());
        $confirme = $breakdown->firstWhere('status', 'confirme');
        $this->assertSame(2, $confirme['count']);
    }

    public function test_top_services_orders_by_count_desc(): void
    {
        $org = OrganizationAccount::factory()->create();

        // Service 1 : 1 booking
        $service1 = \App\Models\ServiceCatalog::create(['name' => 'Service A', 'slug' => 'service-a']);
        // Service 2 : 3 bookings
        $service2 = \App\Models\ServiceCatalog::create(['name' => 'Service B', 'slug' => 'service-b']);

        $this->makeBooking(['customer_organization_id' => $org->id, 'service_catalog_id' => $service1->id]);
        $this->makeBooking(['customer_organization_id' => $org->id, 'service_catalog_id' => $service2->id]);
        $this->makeBooking(['customer_organization_id' => $org->id, 'service_catalog_id' => $service2->id]);
        $this->makeBooking(['customer_organization_id' => $org->id, 'service_catalog_id' => $service2->id]);

        $kpis = app(AnalyticsKpiService::class);
        $top = $kpis->topServices(
            $org->id,
            CarbonImmutable::today()->startOfDay(),
            CarbonImmutable::today()->endOfDay()
        );

        $this->assertSame('Service B', $top->first()['service_name']);
        $this->assertSame(3, $top->first()['count']);
    }

    public function test_org_scope_isolates_data_between_organizations(): void
    {
        $org1 = OrganizationAccount::factory()->create();
        $org2 = OrganizationAccount::factory()->create();

        $this->makeBooking(['customer_organization_id' => $org1->id, 'estimated_price' => 100]);
        $this->makeBooking(['customer_organization_id' => $org2->id, 'estimated_price' => 999]);

        $kpis = app(AnalyticsKpiService::class);

        $result1 = $kpis->mainKpis($org1->id, CarbonImmutable::today()->startOfDay(), CarbonImmutable::today()->endOfDay());
        $result2 = $kpis->mainKpis($org2->id, CarbonImmutable::today()->startOfDay(), CarbonImmutable::today()->endOfDay());

        $this->assertSame(100.0, $result1['revenue']['value']);
        $this->assertSame(999.0, $result2['revenue']['value']);
    }

    // ──────────────────────────────────────────────────────
    // AnalyticsExporter
    // ──────────────────────────────────────────────────────

    public function test_kpis_csv_export_returns_streamed_response(): void
    {
        $org = OrganizationAccount::factory()->create();

        $response = app(AnalyticsExporter::class)->kpisCsv(
            $org->id,
            CarbonImmutable::today()->startOfDay(),
            CarbonImmutable::today()->endOfDay()
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('kpis_', $response->headers->get('Content-Disposition'));
    }

    public function test_monthly_revenue_csv_includes_all_months(): void
    {
        $org = OrganizationAccount::factory()->create();
        $this->makeBooking(['customer_organization_id' => $org->id, 'estimated_price' => 250]);

        ob_start();
        app(AnalyticsExporter::class)->monthlyRevenueCsv($org->id, 12)->sendContent();
        $csv = ob_get_clean();

        // Header + 12 lignes (au moins)
        $lines = explode("\n", trim($csv));
        $this->assertGreaterThanOrEqual(13, count($lines));
        $this->assertStringContainsString('Mois', $lines[0]);
    }
}

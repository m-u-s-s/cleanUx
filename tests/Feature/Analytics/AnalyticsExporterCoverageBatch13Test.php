<?php

namespace Tests\Feature\Analytics;

use App\Models\Booking;
use App\Models\OrganizationAccount;
use App\Services\Analytics\AnalyticsExporter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class AnalyticsExporterCoverageBatch13Test extends TestCase
{
    use RefreshDatabase;

    private function renderStream(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return ob_get_clean();
    }

    public function test_kpis_csv_streams_indicator_rows_with_filename_header(): void
    {
        $from = CarbonImmutable::parse('2026-01-01');
        $to = CarbonImmutable::parse('2026-01-31');

        Booking::factory()->termine()->create([
            'scheduled_date' => '2026-01-10',
            'estimated_price' => 120,
            'status' => 'termine',
        ]);

        $exporter = app(AnalyticsExporter::class);
        $response = $exporter->kpisCsv(null, $from, $to);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString(
            'kpis_2026-01-01_2026-01-31.csv',
            (string) $response->headers->get('Content-Disposition')
        );
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        $csv = $this->renderStream($response);

        $this->assertStringContainsString('Indicateur', $csv);
        $this->assertStringContainsString("Chiffre d'affaires", $csv);
        $this->assertStringContainsString('Nombre de rendez-vous', $csv);
        $this->assertStringContainsString("Taux d'annulation", $csv);
        $this->assertStringContainsString('Satisfaction moyenne', $csv);
        $this->assertStringContainsString('Sites actifs', $csv);
        // BOM emitted for Excel compatibility.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
    }

    public function test_monthly_revenue_csv_streams_month_rows(): void
    {
        Booking::factory()->termine()->create([
            'scheduled_date' => CarbonImmutable::now()->toDateString(),
            'estimated_price' => 80,
            'status' => 'termine',
        ]);

        $exporter = app(AnalyticsExporter::class);
        $response = $exporter->monthlyRevenueCsv(null, 3);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString(
            'revenue_mensuel_3m.csv',
            (string) $response->headers->get('Content-Disposition')
        );

        $csv = $this->renderStream($response);

        $this->assertStringContainsString('Mois', $csv);
        $this->assertStringContainsString('Libellé', $csv);
        $this->assertStringContainsString('Chiffre', $csv);
    }

    public function test_bookings_detailed_csv_renders_completed_and_cancelled_rows(): void
    {
        $from = CarbonImmutable::parse('2026-03-01');
        $to = CarbonImmutable::parse('2026-03-31');

        // Sparse booking: address/city/surface/price null -> exercises empty-string branches.
        $completed = Booking::factory()->termine()->create([
            'scheduled_date' => '2026-03-12',
            'scheduled_time' => '14:30:00',
            'status' => 'termine',
            'address' => null,
            'city' => null,
            'postal_code' => null,
            'surface_m2' => null,
            'estimated_price' => null,
        ]);

        // Fully populated cancelled booking -> exercises truthy formatting branches.
        $cancelled = Booking::factory()->create([
            'scheduled_date' => '2026-03-20',
            'scheduled_time' => '09:00:00',
            'status' => 'annule',
            'address' => '12 rue du Test',
            'city' => 'Bruxelles',
            'postal_code' => '1000',
            'surface_m2' => 75,
            'estimated_price' => 249.50,
            'booking_mode' => 'scheduled',
        ]);

        $exporter = app(AnalyticsExporter::class);
        $response = $exporter->bookingsDetailedCsv(null, $from, $to);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString(
            'bookings_detailed_2026-03-01_2026-03-31.csv',
            (string) $response->headers->get('Content-Disposition')
        );

        $csv = $this->renderStream($response);

        $this->assertStringContainsString('Référence', $csv);
        $this->assertStringContainsString('Surface (m²)', $csv);
        $this->assertStringContainsString($completed->booking_reference, $csv);
        $this->assertStringContainsString($cancelled->booking_reference, $csv);
        $this->assertStringContainsString('12 rue du Test', $csv);
        $this->assertStringContainsString('Bruxelles', $csv);
        $this->assertStringContainsString('14:30', $csv);
        $this->assertStringContainsString('249,50', $csv);
    }

    public function test_bookings_detailed_csv_applies_organization_filter(): void
    {
        $from = CarbonImmutable::parse('2026-04-01');
        $to = CarbonImmutable::parse('2026-04-30');

        $account = OrganizationAccount::factory()->create();

        $scoped = Booking::factory()->create([
            'scheduled_date' => '2026-04-15',
            'status' => 'confirme',
            'customer_organization_id' => $account->id,
        ]);

        $other = Booking::factory()->create([
            'scheduled_date' => '2026-04-16',
            'status' => 'confirme',
            'customer_organization_id' => null,
        ]);

        $exporter = app(AnalyticsExporter::class);
        $response = $exporter->bookingsDetailedCsv($account->id, $from, $to);

        $csv = $this->renderStream($response);

        $this->assertStringContainsString($scoped->booking_reference, $csv);
        $this->assertStringNotContainsString($other->booking_reference, $csv);
    }
}

<?php

namespace Tests\Feature;

use App\Livewire\Admin\AnalyticsCenter;
use App\Models\Booking;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/**
 * The Blade view livewire.admin.analytics-center embeds a raw SQL query that
 * references a non-existent "bookings"."id" column (the real table is
 * rendez_vous), so a full Livewire render throws regardless of seeding and
 * cannot be exercised without editing the view (out of scope). This suite
 * therefore drives the component's own PHP logic directly: mount defaults,
 * the updating* pagination hooks, every computed analytics property, the
 * filter combinations in baseQuery(), and the CSV export action.
 */
class AdminAnalyticsCenterCoverageTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    private function makeComponent(string $dateFrom = '2000-01-01', string $dateTo = '2100-12-31'): AnalyticsCenter
    {
        $component = new AnalyticsCenter;
        $component->mount();
        $component->dateFrom = $dateFrom;
        $component->dateTo = $dateTo;

        return $component;
    }

    public function test_mount_defaults_blank_dates_to_current_month(): void
    {
        $component = new AnalyticsCenter;
        $component->mount();

        $this->assertSame(now()->startOfMonth()->toDateString(), $component->dateFrom);
        $this->assertSame(now()->endOfMonth()->toDateString(), $component->dateTo);
    }

    public function test_computed_properties_aggregate_seeded_bookings(): void
    {
        $context = $this->createCoverageContext();
        $client = User::factory()->client()->create();
        $employee = User::factory()->employe()->create();

        $completed = Booking::factory()->termine()->create([
            'client_id' => $client->id,
            'employe_id' => $employee->id,
            'service_catalog_id' => $context['service']->id,
            'service_zone_id' => $context['zone']->id,
            'postal_code_id' => $context['postalCode']->id,
            'devis_estime' => 120,
            'duree_reelle' => 95,
            'date' => now()->toDateString(),
        ]);

        Feedback::create([
            'booking_id' => $completed->id,
            'rendez_vous_id' => $completed->id,
            'client_id' => $client->id,
            'employe_id' => $employee->id,
            'note' => 5,
            'direction' => 'client_to_provider',
            'status' => 'published',
        ]);

        Booking::factory()->refuse()->create([
            'client_id' => $client->id,
            'employe_id' => $employee->id,
            'service_catalog_id' => $context['service']->id,
            'service_zone_id' => $context['zone']->id,
            'postal_code_id' => $context['postalCode']->id,
            'devis_estime' => 80,
            'date' => now()->toDateString(),
        ]);

        Booking::factory()->entreprise()->confirme()->create([
            'date' => now()->toDateString(),
        ]);

        $component = $this->makeComponent();

        $kpis = $component->getKpisProperty();
        $this->assertSame(3, $kpis['count']);
        $this->assertSame(1, $kpis['completed']);
        $this->assertSame(1, $kpis['cancelled']);
        $this->assertSame(5.0, (float) $kpis['avg_satisfaction']);
        $this->assertArrayHasKey('turnover', $kpis);
        $this->assertArrayHasKey('outstanding_balance', $kpis);

        $this->assertGreaterThanOrEqual(1, $component->getZoneAnalyticsProperty()->count());
        $this->assertGreaterThanOrEqual(1, $component->getServiceAnalyticsProperty()->count());
        $this->assertGreaterThanOrEqual(1, $component->getEmployeeAnalyticsProperty()->count());
        $this->assertGreaterThanOrEqual(1, $component->getClientAnalyticsProperty()->count());
        $this->assertGreaterThanOrEqual(1, $component->getHeatmapZonesProperty()->count());
        $this->assertNotNull($component->getMonthTrendProperty());

        // Option lists used by the filter UI.
        $this->assertGreaterThanOrEqual(1, $component->getZonesProperty()->count());
        $this->assertGreaterThanOrEqual(1, $component->getServicesProperty()->count());
        $this->assertGreaterThanOrEqual(1, $component->getEmployeesProperty()->count());

        // Paginated rows feed.
        $this->assertSame(3, $component->getRowsProperty()->total());
    }

    public function test_filters_narrow_the_result_set_and_csv_exports(): void
    {
        $context = $this->createCoverageContext();
        $client = User::factory()->client()->create();
        $employee = User::factory()->employe()->create();

        $booking = Booking::factory()->termine()->create([
            'client_id' => $client->id,
            'employe_id' => $employee->id,
            'service_catalog_id' => $context['service']->id,
            'service_zone_id' => $context['zone']->id,
            'postal_code_id' => $context['postalCode']->id,
            'devis_estime' => 150,
            'date' => now()->toDateString(),
        ]);

        $component = $this->makeComponent();
        $component->status = 'termine';
        $component->market = 'particulier';
        $component->zoneId = (string) $context['zone']->id;
        $component->serviceId = (string) $context['service']->id;
        $component->employeeId = (string) $employee->id;
        $component->search = $booking->booking_reference;

        $this->assertSame(1, $component->getKpisProperty()['count']);

        $response = $component->exportAnalyticsCsv();
        $this->assertInstanceOf(StreamedResponse::class, $response);

        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString('reference', $csv);
        $this->assertStringContainsString($booking->booking_reference, $csv);
        $this->assertStringContainsString('Particulier', $csv);
    }

    public function test_entreprise_market_filter_and_pagination_hooks(): void
    {
        Booking::factory()->entreprise()->confirme()->create([
            'date' => now()->toDateString(),
        ]);

        $component = $this->makeComponent();
        $component->market = 'entreprise';

        $this->assertSame(1, $component->getKpisProperty()['count']);

        // Exercise the updating* hooks that reset pagination.
        $component->updatingSearch();
        $component->updatingDateFrom();
        $component->updatingDateTo();
        $component->updatingStatus();
        $component->updatingZoneId();
        $component->updatingServiceId();
        $component->updatingEmployeeId();
        $component->updatingMarket();

        $this->assertSame(1, $component->getRowsProperty()->total());
    }

    public function test_empty_result_set_returns_zeroed_kpis(): void
    {
        $component = $this->makeComponent('2000-01-01', '2000-01-31');
        $component->market = 'entreprise';

        $kpis = $component->getKpisProperty();
        $this->assertSame(0, $kpis['count']);
        $this->assertSame(0, $kpis['conversion_rate']);
        $this->assertSame(0, $kpis['avg_satisfaction']);
        $this->assertSame(0.0, (float) $kpis['turnover']);

        $this->assertCount(0, $component->getZoneAnalyticsProperty());
        $this->assertCount(0, $component->getClientAnalyticsProperty());
        $this->assertCount(0, $component->getHeatmapZonesProperty());
    }
}

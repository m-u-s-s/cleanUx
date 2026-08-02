<?php

namespace Tests\Feature\Analytics;

use App\Models\Booking;
use App\Models\CustomerClaim;
use App\Models\Mission;
use App\Models\User;
use App\Services\Analytics\BusinessDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessDashboardServiceCoverageBatch16Test extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_returns_zeroed_structure_when_no_data(): void
    {
        $metrics = app(BusinessDashboardService::class)->metrics();

        $this->assertSame(0.0, $metrics['revenue_current_month']);
        $this->assertSame(0.0, $metrics['revenue_previous_month']);
        $this->assertSame(0.0, $metrics['revenue_growth']);
        $this->assertSame(0, $metrics['bookings_current_month']);
        $this->assertSame(0, $metrics['missions_completed_current_month']);
        $this->assertSame(0, $metrics['clients_total']);
        $this->assertSame(0, $metrics['premium_clients']);
        $this->assertSame(0, $metrics['employees_total']);
        $this->assertSame(0, $metrics['open_claims']);
        $this->assertSame(0.0, $metrics['avg_booking_value']);

        $this->assertCount(6, $metrics['weekly_revenue']);
        foreach ($metrics['weekly_revenue'] as $week) {
            $this->assertArrayHasKey('label', $week);
            $this->assertArrayHasKey('revenue', $week);
            $this->assertArrayHasKey('bookings', $week);
            $this->assertSame(0.0, $week['revenue']);
            $this->assertSame(0, $week['bookings']);
        }
    }

    public function test_metrics_aggregates_current_month_activity(): void
    {
        // AUJOURD'HUI, et non le 3 du mois : le service ne compte le chiffre d'affaires que
        // jusqu'à maintenant. Une donnée posée au 3 est DANS LE FUTUR les 1er et 2, et le test
        // échouait donc deux jours par mois — assez rare pour passer pour un aléa, assez fréquent
        // pour laisser la CI rouge sans que personne ne cherche.
        $currentDate = now()->format('Y-m-d');

        Booking::factory()->create([
            'date' => $currentDate,
            'status' => 'termine',
            'devis_estime' => 200,
        ]);
        Booking::factory()->create([
            'date' => $currentDate,
            'status' => 'confirme',
            'devis_estime' => 100,
        ]);
        // Pending booking: counted in bookings + avg, excluded from revenue.
        Booking::factory()->create([
            'date' => $currentDate,
            'status' => 'en_attente',
            'devis_estime' => 50,
        ]);

        Mission::factory()->completed()->create();

        User::factory()->premiumClient()->create();
        User::factory()->client()->create();
        User::factory()->entreprise()->create();
        User::factory()->employe()->create();

        CustomerClaim::query()->create([
            'client_id' => User::factory()->client()->create()->id,
            'category' => 'quality',
            'priority' => 'normal',
            'status' => 'open',
            'title' => 'Open claim',
            'description' => 'Still open',
        ]);

        $metrics = app(BusinessDashboardService::class)->metrics();

        $this->assertSame(300.0, $metrics['revenue_current_month']);
        $this->assertSame(0.0, $metrics['revenue_previous_month']);
        // current > 0, previous == 0 -> 100.
        $this->assertSame(100.0, $metrics['revenue_growth']);
        $this->assertSame(3, $metrics['bookings_current_month']);
        $this->assertSame(1, $metrics['missions_completed_current_month']);
        // premiumClient (client) + standard client + entreprise (factories may add more).
        $this->assertGreaterThanOrEqual(3, $metrics['clients_total']);
        // premiumClient + entreprise are premium/active.
        $this->assertGreaterThanOrEqual(2, $metrics['premium_clients']);
        $this->assertGreaterThanOrEqual(1, $metrics['employees_total']);
        $this->assertGreaterThanOrEqual(1, $metrics['open_claims']);
        $this->assertSame(116.67, $metrics['avg_booking_value']);
    }

    public function test_metrics_computes_proportional_revenue_growth(): void
    {
        // AUJOURD'HUI, et non le 3 du mois : le service ne compte le chiffre d'affaires que
        // jusqu'à maintenant. Une donnée posée au 3 est DANS LE FUTUR les 1er et 2, et le test
        // échouait donc deux jours par mois — assez rare pour passer pour un aléa, assez fréquent
        // pour laisser la CI rouge sans que personne ne cherche.
        $currentDate = now()->format('Y-m-d');
        // Le mois précédent est entièrement écoulé : n'importe quel jour convient, mais on reste
        // au début pour rester valide même en fin de mois court.
        $previousDate = now()->subMonth()->startOfMonth()->format('Y-m-d');

        Booking::factory()->create([
            'date' => $previousDate,
            'status' => 'termine',
            'devis_estime' => 100,
        ]);
        Booking::factory()->create([
            'date' => $currentDate,
            'status' => 'termine',
            'devis_estime' => 150,
        ]);

        $metrics = app(BusinessDashboardService::class)->metrics();

        $this->assertSame(150.0, $metrics['revenue_current_month']);
        $this->assertSame(100.0, $metrics['revenue_previous_month']);
        $this->assertSame(50.0, $metrics['revenue_growth']);
    }
}

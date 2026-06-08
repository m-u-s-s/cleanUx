<?php

namespace Tests\Unit\Assistant;

use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Models\LoyaltyAccount;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\ProviderPayout;
use App\Models\User;
use App\Services\Assistant\Actions\AssistantReadActions;
use Database\Factories\AvailabilitySlotFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[CoversClass(AssistantReadActions::class)]
#[Group('requires-mysql')]
class AssistantReadActionsTest extends TestCase
{
    use RefreshDatabase;

    private AssistantReadActions $actions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actions = new AssistantReadActions;
    }

    // ──────────────────────────────────────────────────────
    // execute() dispatcher
    // ──────────────────────────────────────────────────────

    public function test_execute_unknown_action_returns_unknown_message(): void
    {
        $user = User::factory()->client()->create();

        $result = $this->actions->execute('does_not_exist', $user);

        $this->assertStringContainsString('Action inconnue', $result);
        $this->assertStringContainsString('does_not_exist', $result);
    }

    // ──────────────────────────────────────────────────────
    // list_bookings
    // ──────────────────────────────────────────────────────

    public function test_list_bookings_empty_returns_no_booking_message(): void
    {
        $user = User::factory()->client()->create();

        $result = $this->actions->execute('list_bookings', $user);

        $this->assertStringContainsString('aucune réservation', $result);
    }

    public function test_list_bookings_returns_recent_bookings_for_customer(): void
    {
        $user = User::factory()->client()->create();
        $booking = Booking::factory()->create([
            'customer_user_id' => $user->id,
            'status' => 'confirme',
            'booking_reference' => 'CUX-READ-01',
            'city' => 'Bruxelles',
        ]);

        $result = $this->actions->execute('list_bookings', $user);

        $this->assertStringContainsString('5 dernières réservations', $result);
        $this->assertStringContainsString('CUX-READ-01', $result);
        $this->assertStringContainsString('confirme', $result);
        $this->assertStringContainsString('Bruxelles', $result);
        $this->assertNotNull($booking->id);
    }

    // ──────────────────────────────────────────────────────
    // booking_detail
    // ──────────────────────────────────────────────────────

    public function test_booking_detail_without_id_asks_for_reference(): void
    {
        $user = User::factory()->client()->create();

        $result = $this->actions->execute('booking_detail', $user, ['booking_id' => null]);

        $this->assertStringContainsString("l'identifiant", $result);
    }

    public function test_booking_detail_not_found_returns_error(): void
    {
        $user = User::factory()->client()->create();

        $result = $this->actions->execute('booking_detail', $user, ['booking_id' => 999999]);

        $this->assertStringContainsString('introuvable', $result);
    }

    public function test_booking_detail_returns_formatted_detail_for_owner(): void
    {
        $user = User::factory()->client()->create();
        $booking = Booking::factory()->create([
            'customer_user_id' => $user->id,
            'status' => 'confirme',
            'booking_reference' => 'CUX-DETAIL-01',
            'scheduled_date' => '2026-07-15',
            'scheduled_time' => '09:30:00',
            'address' => '10 rue de la Paix',
            'city' => 'Bruxelles',
            'estimated_price' => 149.90,
            'payment_status' => 'authorized',
        ]);

        $result = $this->actions->execute('booking_detail', $user, ['booking_id' => $booking->id]);

        $this->assertStringContainsString('CUX-DETAIL-01', $result);
        $this->assertStringContainsString('Statut : confirme', $result);
        $this->assertStringContainsString('Bruxelles', $result);
        $this->assertStringContainsString('149,90', $result);
        $this->assertStringContainsString('authorized', $result);
    }

    public function test_booking_detail_lookup_by_reference(): void
    {
        $user = User::factory()->client()->create();
        Booking::factory()->create([
            'customer_user_id' => $user->id,
            'booking_reference' => 'CUX-BYREF-99',
            'status' => 'en_attente',
        ]);

        $result = $this->actions->execute('booking_detail', $user, ['booking_id' => 'CUX-BYREF-99']);

        $this->assertStringContainsString('CUX-BYREF-99', $result);
        $this->assertStringContainsString('en_attente', $result);
    }

    // ──────────────────────────────────────────────────────
    // loyalty_balance
    // ──────────────────────────────────────────────────────

    public function test_loyalty_balance_without_account_returns_message(): void
    {
        $user = User::factory()->client()->create();

        $result = $this->actions->execute('loyalty_balance', $user);

        $this->assertStringContainsString('pas encore de compte fidélité', $result);
    }

    public function test_loyalty_balance_returns_points_and_default_tier(): void
    {
        $user = User::factory()->client()->create();
        LoyaltyAccount::factory()->create([
            'user_id' => $user->id,
            'redeemable_points' => 320,
            'period_points' => 80,
            'lifetime_points' => 1200,
        ]);

        $result = $this->actions->execute('loyalty_balance', $user);

        $this->assertStringContainsString('compte fidélité', $result);
        $this->assertStringContainsString('Bronze', $result);
        $this->assertStringContainsString('320 pts', $result);
        $this->assertStringContainsString('80 pts', $result);
        $this->assertStringContainsString('1200 pts', $result);
    }

    // ──────────────────────────────────────────────────────
    // next_mission
    // ──────────────────────────────────────────────────────

    public function test_next_mission_none_returns_message(): void
    {
        $user = User::factory()->employe()->create();

        $result = $this->actions->execute('next_mission', $user);

        $this->assertStringContainsString('aucune mission prévue', $result);
    }

    public function test_next_mission_returns_upcoming_mission(): void
    {
        $user = User::factory()->employe()->create();
        $mission = Mission::factory()->create([
            'status' => 'assigned',
            'planned_start_at' => now()->addDay(),
        ]);
        MissionAssignment::factory()->create([
            'mission_id' => $mission->id,
            'user_id' => $user->id,
        ]);

        $result = $this->actions->execute('next_mission', $user);

        $this->assertStringContainsString('Prochaine mission', $result);
        $this->assertStringContainsString('Statut : assigned', $result);
        $this->assertStringContainsString(now()->addDay()->format('d/m/Y'), $result);
    }

    // ──────────────────────────────────────────────────────
    // earnings_summary
    // ──────────────────────────────────────────────────────

    public function test_earnings_summary_aggregates_payouts(): void
    {
        $user = User::factory()->employe()->create();

        ProviderPayout::factory()->create([
            'provider_user_id' => $user->id,
            'status' => ProviderPayout::STATUS_PAID,
            'amount' => 120.50,
            'period_end' => now()->toDateString(),
        ]);
        ProviderPayout::factory()->create([
            'provider_user_id' => $user->id,
            'status' => ProviderPayout::STATUS_PENDING,
            'amount' => 30.00,
            'period_end' => now()->toDateString(),
        ]);

        $result = $this->actions->execute('earnings_summary', $user);

        $this->assertStringContainsString('Résumé de vos revenus', $result);
        $this->assertStringContainsString('120,50', $result);
        $this->assertStringContainsString('30,00', $result);
    }

    public function test_earnings_summary_with_no_payouts_returns_zeros(): void
    {
        $user = User::factory()->employe()->create();

        $result = $this->actions->execute('earnings_summary', $user);

        $this->assertStringContainsString('Cette semaine : 0,00', $result);
        $this->assertStringContainsString('Ce mois : 0,00', $result);
    }

    // ──────────────────────────────────────────────────────
    // availability_slots
    // ──────────────────────────────────────────────────────

    public function test_availability_slots_empty_returns_message(): void
    {
        $user = User::factory()->employe()->create();

        $result = $this->actions->execute('availability_slots', $user);

        $this->assertStringContainsString('aucun créneau', $result);
    }

    public function test_availability_slots_lists_configured_slots(): void
    {
        $user = User::factory()->employe()->create();
        AvailabilitySlotFactory::new()->create([
            'provider_user_id' => $user->id,
            'weekday' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_active' => true,
        ]);

        $result = $this->actions->execute('availability_slots', $user);

        $this->assertStringContainsString('créneaux de disponibilité', $result);
        $this->assertStringContainsString('Lundi', $result);
        $this->assertStringContainsString('09:00', $result);
        $this->assertStringContainsString('17:00', $result);
    }

    // ──────────────────────────────────────────────────────
    // platform_kpis + countOpenDisputes
    // ──────────────────────────────────────────────────────

    public function test_platform_kpis_returns_formatted_report(): void
    {
        $client = User::factory()->client()->create();
        Booking::factory()->create(['customer_user_id' => $client->id]);
        ComplaintCase::factory()->create(['status' => 'open']);

        $result = $this->actions->execute('platform_kpis', $client);

        $this->assertStringContainsString('KPIs plateforme', $result);
        $this->assertStringContainsString("Réservations créées aujourd'hui", $result);
        $this->assertStringContainsString('Missions actives', $result);
        $this->assertStringContainsString('Litiges ouverts', $result);
    }

    public function test_count_open_disputes_counts_open_cases(): void
    {
        ComplaintCase::factory()->create(['status' => 'open']);
        ComplaintCase::factory()->create(['status' => 'escalated']);
        ComplaintCase::factory()->create(['status' => 'resolved']);

        $this->assertSame(2, $this->actions->countOpenDisputes());
    }

    // ──────────────────────────────────────────────────────
    // trade_stats
    // ──────────────────────────────────────────────────────

    public function test_trade_stats_empty_returns_message(): void
    {
        $result = $this->actions->execute('trade_stats', User::factory()->admin()->create());

        $this->assertStringContainsString('Aucune donnée', $result);
    }

    public function test_trade_stats_groups_bookings_by_service_catalog(): void
    {
        $client = User::factory()->client()->create();
        Booking::factory()->create(['customer_user_id' => $client->id]);

        $result = $this->actions->execute('trade_stats', $client);

        $this->assertStringContainsString('Réservations par métier', $result);
        $this->assertStringContainsString('réservation(s)', $result);
    }
}

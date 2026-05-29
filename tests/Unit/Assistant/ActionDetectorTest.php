<?php

namespace Tests\Unit\Assistant;

use App\Models\User;
use App\Services\Assistant\Actions\ActionDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @covers \App\Services\Assistant\Actions\ActionDetector
 */
class ActionDetectorTest extends TestCase
{
    use RefreshDatabase;

    private ActionDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new ActionDetector;
    }

    public function test_booking_keyword_triggers_list_bookings_for_client(): void
    {
        $user = User::factory()->client()->create();

        $actions = $this->detector->detectActions('Montre-moi mes réservations', $user);

        $this->assertContains('list_bookings', $actions);
    }

    public function test_loyalty_keyword_triggers_loyalty_balance_for_client(): void
    {
        $user = User::factory()->client()->create();

        $actions = $this->detector->detectActions('Combien de points fidélité ai-je ?', $user);

        $this->assertContains('loyalty_balance', $actions);
    }

    public function test_mission_keyword_triggers_next_mission_for_employe(): void
    {
        $user = User::factory()->employe()->create();

        $actions = $this->detector->detectActions('Quelle est ma prochaine mission ?', $user);

        $this->assertContains('next_mission', $actions);
    }

    public function test_earnings_keyword_triggers_earnings_summary_for_employe(): void
    {
        $user = User::factory()->employe()->create();

        $actions = $this->detector->detectActions('Combien ai-je gagné ce mois ?', $user);

        $this->assertContains('earnings_summary', $actions);
    }

    public function test_kpi_keyword_triggers_platform_kpis_for_admin(): void
    {
        $user = User::factory()->admin()->create();

        $actions = $this->detector->detectActions('Montre-moi les KPI du jour', $user);

        $this->assertContains('platform_kpis', $actions);
    }

    public function test_booking_keyword_does_not_trigger_list_bookings_for_employe(): void
    {
        // bookings are client-scoped; an employe asking about "mission" should not get list_bookings
        $user = User::factory()->employe()->create();

        $actions = $this->detector->detectActions('mes réservations', $user);

        $this->assertNotContains('list_bookings', $actions);
    }

    public function test_kpi_keyword_does_not_trigger_for_non_admin(): void
    {
        $user = User::factory()->client()->create();

        $actions = $this->detector->detectActions('statistiques plateforme', $user);

        $this->assertNotContains('platform_kpis', $actions);
    }

    public function test_unrelated_message_returns_empty_array(): void
    {
        $user = User::factory()->client()->create();

        $actions = $this->detector->detectActions('Bonjour, comment allez-vous ?', $user);

        $this->assertSame([], $actions);
    }

    public function test_multiple_keywords_returns_deduplicated_actions(): void
    {
        $user = User::factory()->client()->create();

        // "réservation" + "booking" both map to list_bookings
        $actions = $this->detector->detectActions('Mes réservations et mes booking', $user);

        $this->assertSame(1, count(array_filter($actions, fn ($a) => $a === 'list_bookings')));
    }

    public function test_trade_keyword_triggers_trade_stats_for_admin(): void
    {
        $user = User::factory()->admin()->create();

        $actions = $this->detector->detectActions('Statistiques par métier', $user);

        $this->assertContains('trade_stats', $actions);
    }
}

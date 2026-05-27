<?php

namespace Tests\Unit\Assistant;

use App\Models\User;
use App\Services\Assistant\Actions\AssistantActionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @covers \App\Services\Assistant\Actions\AssistantActionRegistry
 */
class AssistantActionRegistryTest extends TestCase
{
    use RefreshDatabase;

    private AssistantActionRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new AssistantActionRegistry();
    }

    public function test_client_user_gets_booking_and_loyalty_actions(): void
    {
        $user = User::factory()->client()->create();

        $names = $this->actionNames($user);

        $this->assertContains('list_bookings', $names);
        $this->assertContains('booking_detail', $names);
        $this->assertContains('loyalty_balance', $names);
    }

    public function test_client_user_does_not_get_provider_or_admin_actions(): void
    {
        $user = User::factory()->client()->create();

        $names = $this->actionNames($user);

        $this->assertNotContains('next_mission', $names);
        $this->assertNotContains('earnings_summary', $names);
        $this->assertNotContains('platform_kpis', $names);
        $this->assertNotContains('trade_stats', $names);
    }

    public function test_employe_user_gets_provider_specific_actions(): void
    {
        $user = User::factory()->employe()->create();

        $names = $this->actionNames($user);

        $this->assertContains('next_mission', $names);
        $this->assertContains('earnings_summary', $names);
        $this->assertContains('availability_slots', $names);
    }

    public function test_employe_user_does_not_get_client_or_admin_actions(): void
    {
        $user = User::factory()->employe()->create();

        $names = $this->actionNames($user);

        $this->assertNotContains('list_bookings', $names);
        $this->assertNotContains('platform_kpis', $names);
        $this->assertNotContains('trade_stats', $names);
    }

    public function test_admin_user_gets_platform_kpi_and_trade_stats_actions(): void
    {
        $user = User::factory()->admin()->create();

        $names = $this->actionNames($user);

        $this->assertContains('platform_kpis', $names);
        $this->assertContains('trade_stats', $names);
    }

    public function test_to_prompt_block_returns_empty_string_for_user_with_no_actions(): void
    {
        // A user with no role has no actions — we stub a user with no profile
        $user = User::factory()->create(['role' => null, 'platform_role' => null]);

        $block = $this->registry->toPromptBlock($user);

        $this->assertSame('', $block);
    }

    public function test_to_prompt_block_lists_action_names_for_client(): void
    {
        $user = User::factory()->client()->create();

        $block = $this->registry->toPromptBlock($user);

        $this->assertStringContainsString('list_bookings', $block);
        $this->assertStringContainsString('loyalty_balance', $block);
    }

    // ──────────────────────────────────────────────────────

    private function actionNames(User $user): array
    {
        return array_column($this->registry->availableActions($user), 'name');
    }
}

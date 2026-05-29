<?php

namespace Tests\Feature\Assistant;

use App\Models\OrganizationAccount;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Assistant\QuickActions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickActionsTest extends TestCase
{
    use RefreshDatabase;

    private QuickActions $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(QuickActions::class);
    }

    // ──────────────────────────────────────────────────────
    // Structure contract
    // ──────────────────────────────────────────────────────

    public function test_each_action_has_label_and_prompt(): void
    {
        $user = User::factory()->create();
        $actions = $this->service->forUser($user);

        $this->assertNotEmpty($actions);

        foreach ($actions as $action) {
            $this->assertArrayHasKey('label', $action);
            $this->assertArrayHasKey('prompt', $action);
            $this->assertNotEmpty($action['label']);
            $this->assertNotEmpty($action['prompt']);
        }
    }

    public function test_returns_three_actions_for_every_role(): void
    {
        $user = User::factory()->create();
        $actions = $this->service->forUser($user);

        $this->assertCount(3, $actions);
    }

    // ──────────────────────────────────────────────────────
    // Role branching
    // ──────────────────────────────────────────────────────

    public function test_admin_gets_kpis_action(): void
    {
        $user = User::factory()->create(['platform_role' => 'admin']);

        $labels = array_column($this->service->forUser($user), 'label');

        $this->assertContains('KPIs du jour', $labels);
        $this->assertContains('Litiges en cours', $labels);
    }

    public function test_company_client_via_org_account_gets_nouveau_service(): void
    {
        $org = OrganizationAccount::factory()->create();
        $user = User::factory()->create(['organization_account_id' => $org->id]);

        $labels = array_column($this->service->forUser($user), 'label');

        $this->assertContains('Nouveau service', $labels);
        $this->assertContains('Factures', $labels);
    }

    public function test_provider_employe_gets_mission_actions(): void
    {
        $user = User::factory()->create();
        ProviderProfile::factory()->create([
            'user_id' => $user->id,
            'provider_type' => 'independent',
        ]);
        $user->refresh();

        $labels = array_column($this->service->forUser($user), 'label');

        $this->assertContains('Prochaine mission', $labels);
        $this->assertContains('Mes revenus', $labels);
    }

    public function test_default_client_personal_gets_booking_and_loyalty_actions(): void
    {
        $user = User::factory()->create();

        $actions = $this->service->forUser($user);
        $labels = array_column($actions, 'label');
        $prompts = array_column($actions, 'prompt');

        // Must contain a booking-related action
        $hasBooking = (bool) array_filter($labels, fn ($l) => str_contains(mb_strtolower($l), 'server'));
        $this->assertTrue($hasBooking, 'Expected a booking quick action for default client');

        // Must contain a loyalty-related action
        $hasLoyalty = (bool) array_filter($labels, fn ($l) => str_contains(mb_strtolower($l), 'fid'));
        $this->assertTrue($hasLoyalty, 'Expected a loyalty quick action for default client');

        // Prompts must be non-trivial sentences
        foreach ($prompts as $prompt) {
            $this->assertGreaterThan(10, mb_strlen($prompt));
        }
    }

    // ──────────────────────────────────────────────────────
    // Admin takes priority over company checks
    // ──────────────────────────────────────────────────────

    public function test_admin_with_org_gets_admin_actions_not_company(): void
    {
        $org = OrganizationAccount::factory()->create();
        $user = User::factory()->create([
            'platform_role' => 'admin',
            'organization_account_id' => $org->id,
        ]);

        $labels = array_column($this->service->forUser($user), 'label');

        $this->assertContains('KPIs du jour', $labels);
        $this->assertNotContains('Nouveau service', $labels);
    }
}

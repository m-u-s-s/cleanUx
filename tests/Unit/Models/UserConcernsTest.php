<?php

namespace Tests\Unit\Models;

use App\Enums\AssistantContextRole;
use App\Models\CustomerProfile;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for User model concern traits:
 *   - HasAdminCapabilities  (isPlatformAdmin, isSuperAdmin, canAccessAdminModule)
 *   - HasUserTypeChecks     (isCustomer, isProvider, assistantContextRole)
 *   - HasBillingFeatures    (isPremium, hasBillingIssue)
 *
 * No HTTP stack involved — all assertions on model instances directly.
 */
class UserConcernsTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────
    // HasAdminCapabilities
    // ─────────────────────────────────────────────────────────────────

    public function test_is_platform_admin_returns_true_for_admin_platform_role(): void
    {
        $user = User::factory()->create(['platform_role' => 'admin']);

        $this->assertTrue($user->isPlatformAdmin());
    }

    public function test_is_platform_admin_returns_true_for_super_admin_platform_role(): void
    {
        $user = User::factory()->create(['platform_role' => 'super_admin']);

        $this->assertTrue($user->isPlatformAdmin());
    }

    public function test_is_platform_admin_returns_false_for_regular_user(): void
    {
        $user = User::factory()->create(['platform_role' => 'user']);

        $this->assertFalse($user->isPlatformAdmin());
    }

    public function test_is_super_admin_returns_true_only_for_super_admin_role(): void
    {
        $admin = User::factory()->create(['platform_role' => 'admin']);
        $superAdmin = User::factory()->create(['platform_role' => 'super_admin']);

        $this->assertFalse($admin->isSuperAdmin());
        $this->assertTrue($superAdmin->isSuperAdmin());
    }

    public function test_can_access_admin_module_returns_false_for_non_admin(): void
    {
        $user = User::factory()->create(['role' => 'client', 'platform_role' => 'user']);

        $this->assertFalse($user->canAccessAdminModule());
    }

    public function test_can_access_admin_module_returns_true_for_super_admin_without_permission_check(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'platform_role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->assertTrue($user->canAccessAdminModule('manage-finance'));
    }

    public function test_can_access_admin_module_returns_false_for_inactive_admin(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'platform_role' => 'admin',
            'is_active' => false,
        ]);

        $this->assertFalse($user->canAccessAdminModule());
    }

    public function test_can_access_admin_module_without_permission_returns_true_for_admin(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'platform_role' => 'admin',
            'is_active' => true,
        ]);

        $this->assertTrue($user->canAccessAdminModule());
    }

    // ─────────────────────────────────────────────────────────────────
    // HasUserTypeChecks
    // ─────────────────────────────────────────────────────────────────

    public function test_is_customer_returns_false_when_no_customer_profile(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->isCustomer());
    }

    public function test_is_customer_returns_true_when_customer_profile_exists(): void
    {
        $user = User::factory()->create();
        CustomerProfile::create(['user_id' => $user->id, 'customer_type' => 'personal']);

        $this->assertTrue($user->isCustomer());
    }

    public function test_is_provider_returns_false_when_no_provider_profile(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->isProvider());
    }

    public function test_is_provider_returns_true_when_provider_profile_exists(): void
    {
        $user = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $user->id, 'provider_type' => 'independent']);

        $this->assertTrue($user->isProvider());
    }

    public function test_assistant_context_role_returns_admin_for_platform_admin(): void
    {
        $user = User::factory()->create(['platform_role' => 'admin', 'is_active' => true]);

        $this->assertSame(AssistantContextRole::ADMIN, $user->assistantContextRole());
    }

    public function test_assistant_context_role_falls_back_to_client_personal_for_plain_user(): void
    {
        $user = User::factory()->create(['platform_role' => 'user']);

        // No customer / provider profiles → fallback
        $this->assertSame(AssistantContextRole::CLIENT_PERSONAL, $user->assistantContextRole());
    }

    public function test_assistant_context_role_returns_client_personal_for_personal_customer(): void
    {
        $user = User::factory()->create(['platform_role' => 'user']);
        CustomerProfile::create(['user_id' => $user->id, 'customer_type' => 'personal']);

        $this->assertSame(AssistantContextRole::CLIENT_PERSONAL, $user->fresh()->assistantContextRole());
    }

    public function test_is_client_company_returns_true_with_organization_context(): void
    {
        $user = User::factory()->create(['organization_account_id' => 1]);

        $this->assertTrue($user->isClientCompany());
    }

    public function test_is_provider_returns_true_when_provider_profile_present(): void
    {
        $user = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $user->id, 'provider_type' => 'independent']);

        $this->assertTrue($user->fresh()->isProvider());
    }

    // ─────────────────────────────────────────────────────────────────
    // HasBillingFeatures
    // ─────────────────────────────────────────────────────────────────

    public function test_is_premium_returns_true_for_active_premium_plan(): void
    {
        $user = User::factory()->create([
            'plan_type' => 'premium',
            'plan_status' => 'active',
        ]);

        $this->assertTrue($user->isPremium());
    }

    public function test_is_premium_returns_true_for_trialing_plan(): void
    {
        $user = User::factory()->create([
            'plan_type' => 'premium',
            'plan_status' => 'trialing',
        ]);

        $this->assertTrue($user->isPremium());
    }

    public function test_is_premium_returns_false_for_standard_plan(): void
    {
        $user = User::factory()->create([
            'plan_type' => 'standard',
            'plan_status' => 'active',
        ]);

        $this->assertFalse($user->isPremium());
    }

    public function test_is_premium_returns_false_when_premium_plan_is_inactive(): void
    {
        $user = User::factory()->create([
            'plan_type' => 'premium',
            'plan_status' => 'inactive',
        ]);

        $this->assertFalse($user->isPremium());
    }

    public function test_has_billing_issue_returns_true_for_past_due_status(): void
    {
        $user = User::factory()->create(['plan_status' => 'past_due']);

        $this->assertTrue($user->hasBillingIssue());
    }

    public function test_has_billing_issue_returns_true_for_unpaid_status(): void
    {
        $user = User::factory()->create(['plan_status' => 'unpaid']);

        $this->assertTrue($user->hasBillingIssue());
    }

    public function test_has_billing_issue_returns_true_for_incomplete_expired(): void
    {
        $user = User::factory()->create(['plan_status' => 'incomplete_expired']);

        $this->assertTrue($user->hasBillingIssue());
    }

    public function test_has_billing_issue_returns_false_for_active_status(): void
    {
        $user = User::factory()->create(['plan_status' => 'active']);

        $this->assertFalse($user->hasBillingIssue());
    }

    public function test_has_billing_issue_returns_false_when_plan_status_is_inactive(): void
    {
        $user = User::factory()->create(['plan_status' => 'inactive']);

        $this->assertFalse($user->hasBillingIssue());
    }
}
